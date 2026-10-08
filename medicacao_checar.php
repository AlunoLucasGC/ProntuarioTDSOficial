<?php
// ===================================================================
//  medicacao_checar.php  —  Checa uma dose
//  Módulo 5 · Sprint 7 · perfil TÉCNICO
//
//  Este arquivo faz duas coisas, e é o único do sistema assim:
//
//    GET   mostra o formulário da justificativa (não grava nada)
//    POST  grava a checagem
//
//  A razão é a justificativa obrigatória. "Administrado" é um clique —
//  não há o que perguntar. "Não administrado" exige um motivo escrito,
//  e motivo precisa de uma tela para ser digitado.
//
//  Três caminhos possíveis:
//
//    POST com administracao_id  -> checa uma dose com hora marcada
//    POST com prescricao_id     -> registra um "se necessário" agora,
//                                  criando a linha já administrada
//    GET  com administracao_id  -> pede a justificativa
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/prescricoes.php';

$quem_checou = $_SESSION['usuario_id'];

// ===================================================================
//  CAMINHO 1 — GET: pedir a justificativa
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    $id = isset($_GET['administracao_id']) ? (int) $_GET['administracao_id'] : 0;

    if ($id == 0) {
        header('Location: medicacao_turno.php?erro=nao_encontrado');
        exit;
    }

    $sql = "SELECT a.id, a.horario_previsto, a.status,
                   pr.dose, pr.via, pr.frequencia,
                   COALESCE(pr.observacao, '') AS observacao,
                   m.nome AS medicamento,
                   COALESCE(m.apresentacao, '') AS apresentacao,
                   p.nome AS paciente,
                   COALESCE(p.alergias, '') AS alergias,
                   TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
                   COALESCE(l.identificacao, '') AS leito
            FROM administracoes a
            JOIN prescricoes  pr ON pr.id = a.prescricao_id
            JOIN medicamentos m  ON m.id = pr.medicamento_id
            JOIN pacientes    p  ON p.id = pr.paciente_id
            LEFT JOIN internacoes i ON i.paciente_id = p.id
                                   AND i.situacao = 'internado' AND i.ativo = 1
            LEFT JOIN leitos  l  ON l.id = i.leito_id
            WHERE a.id = ?";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $dose = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$dose) {
        mysqli_close($conexao);
        header('Location: medicacao_turno.php?erro=nao_encontrado');
        exit;
    }

    if ($dose['status'] != 'pendente') {
        mysqli_close($conexao);
        header('Location: medicacao_turno.php?erro=ja_checada');
        exit;
    }

    $titulo    = 'Dose não administrada';
    $subtitulo = $dose['paciente'];
    require 'includes/cabecalho.php';
?>

  <?php if (isset($_GET['erro']) && $_GET['erro'] == 'sem_justificativa') { ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
      <i class="icon-base bx bx-error-circle me-2"></i>
      <div>A justificativa é obrigatória. Escreva o motivo.</div>
    </div>
  <?php } ?>

  <div class="row">
    <div class="col-lg-8">

      <div class="card border border-danger">
        <div class="card-body">

          <h5 class="mb-3">Registrar como não administrada</h5>

          <div class="row g-3 mb-3">
            <div class="col-sm-7">
              <small class="text-body-secondary d-block">Paciente</small>
              <strong><?php echo htmlspecialchars($dose['paciente']); ?></strong>,
              <?php echo $dose['idade']; ?> anos
              <?php if ($dose['leito'] != '') { ?>
                · Leito <strong><?php echo htmlspecialchars($dose['leito']); ?></strong>
              <?php } ?>
            </div>
            <div class="col-sm-5">
              <small class="text-body-secondary d-block">Horário previsto</small>
              <strong><?php echo date('d/m/Y H:i', strtotime($dose['horario_previsto'])); ?></strong>
            </div>
          </div>

          <div class="mb-3">
            <small class="text-body-secondary d-block">Medicamento</small>
            <strong><?php echo htmlspecialchars($dose['medicamento']); ?></strong>
            <?php if ($dose['apresentacao'] != '') { ?>
              <small class="text-body-secondary"><?php echo htmlspecialchars($dose['apresentacao']); ?></small>
            <?php } ?>
            <br>
            <?php echo htmlspecialchars($dose['dose']); ?>
            · <?php echo htmlspecialchars($dose['via']); ?>
            <small class="text-body-secondary">(<?php echo nomeDaVia($dose['via']); ?>)</small>
            · <?php echo htmlspecialchars($dose['frequencia']); ?>
          </div>

          <?php
          $texto_alergia = trim($dose['alergias']);
          $nega = negaAlergias($texto_alergia);
          if (!$nega) {
          ?>
            <div class="alerta-alergia mb-3">
              <i class="icon-base bx bx-error-circle"></i>
              <span><?php echo htmlspecialchars($texto_alergia); ?></span>
            </div>
          <?php } ?>

          <form action="medicacao_checar.php" method="post">
            <input type="hidden" name="administracao_id" value="<?php echo $dose['id']; ?>">
            <input type="hidden" name="status" value="nao_administrado">

            <div class="mb-3">
              <label class="form-label" for="justificativa">Justificativa *</label>
              <textarea class="form-control" id="justificativa" name="justificativa"
                        rows="3" required maxlength="255"
                        placeholder="Ex.: paciente recusou a medicação; profissional responsável comunicado"></textarea>
              <div class="form-text">
                Obrigatória. Diga o que houve e a quem foi comunicado.
                A hora e o seu nome são gravados automaticamente.
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-danger">
                Registrar como não administrada
              </button>
              <a href="medicacao_turno.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </form>

        </div>
      </div>

    </div>
  </div>

<?php
    mysqli_close($conexao);
    require 'includes/rodape.php';
    exit;
}

// ===================================================================
//  CAMINHO 2 — POST com prescricao_id: um "se necessário" agora
//
//  Aqui a linha de `administracoes` NÃO existe ainda: ela nasce já
//  administrada, com horario_previsto igual à hora real, porque não
//  havia horário previsto nenhum.
// ===================================================================
if (isset($_POST['prescricao_id'])) {

    $prescricao_id = (int) $_POST['prescricao_id'];

    if ($prescricao_id == 0) {
        mysqli_close($conexao);
        header('Location: medicacao_turno.php?erro=nao_encontrado');
        exit;
    }

    // A prescrição existe, está ativa, é "se necessário", e o paciente
    // está internado? Tudo reconferido agora, não no que a tela mostrou.
    $sql = "SELECT pr.id
            FROM prescricoes pr
            JOIN internacoes i ON i.id = pr.internacao_id
                              AND i.situacao = 'internado' AND i.ativo = 1
            WHERE pr.id = ? AND pr.ativo = 1 AND pr.horarios IS NULL";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $prescricao_id);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$ok) {
        mysqli_close($conexao);
        header('Location: medicacao_turno.php?erro=suspensa');
        exit;
    }

    $sql = "INSERT INTO administracoes
              (prescricao_id, usuario_id, horario_previsto,
               data_hora_checagem, status, justificativa)
            VALUES (?, ?, NOW(), NOW(), 'administrado', NULL)";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $prescricao_id, $quem_checou);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_close($conexao);
    header('Location: medicacao_turno.php?ok=administrado');
    exit;
}

// ===================================================================
//  CAMINHO 3 — POST com administracao_id: checa a dose
// ===================================================================
if (!isset($_POST['administracao_id']) || !isset($_POST['status'])) {
    mysqli_close($conexao);
    header('Location: medicacao_turno.php?erro=nao_encontrado');
    exit;
}

$id     = (int) $_POST['administracao_id'];
$status = trim($_POST['status']);

if ($id == 0) {
    mysqli_close($conexao);
    header('Location: medicacao_turno.php?erro=nao_encontrado');
    exit;
}

// O status só pode ser um dos dois. 'pendente' não é checagem, e
// qualquer outra coisa não existe.
if ($status != 'administrado' && $status != 'nao_administrado') {
    mysqli_close($conexao);
    header('Location: medicacao_turno.php?erro=status_invalido');
    exit;
}

// -------------------------------------------------------------------
//  A JUSTIFICATIVA
//
//  Obrigatória para 'nao_administrado', e ignorada para 'administrado'
//  — uma dose dada não precisa de motivo.
//
//  Esta é a regra que dá sentido ao módulo. Sem ela, o caminho mais
//  fácil para o técnico apressado seria marcar tudo como não
//  administrado e seguir em frente.
// -------------------------------------------------------------------
$justificativa = isset($_POST['justificativa']) ? trim($_POST['justificativa']) : '';

if ($status == 'nao_administrado' && $justificativa === '') {
    mysqli_close($conexao);
    header('Location: medicacao_checar.php?administracao_id=' . $id
         . '&erro=sem_justificativa');
    exit;
}

if ($status == 'administrado') {
    $justificativa = null;
}

// -------------------------------------------------------------------
//  RECONFERÊNCIA
//
//  Entre a tela carregar e o botão ser clicado, outro técnico pode ter
//  checado a mesma dose, ou o médico pode ter suspendido a prescrição.
// -------------------------------------------------------------------
$sql = "SELECT a.id, a.status, pr.ativo AS prescricao_ativa
        FROM administracoes a
        JOIN prescricoes pr ON pr.id = a.prescricao_id
        WHERE a.id = ?";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$dose = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$dose) {
    mysqli_close($conexao);
    header('Location: medicacao_turno.php?erro=nao_encontrado');
    exit;
}

if ($dose['status'] != 'pendente') {
    mysqli_close($conexao);
    header('Location: medicacao_turno.php?erro=ja_checada');
    exit;
}

if (!$dose['prescricao_ativa']) {
    mysqli_close($conexao);
    header('Location: medicacao_turno.php?erro=suspensa');
    exit;
}

// -------------------------------------------------------------------
//  Grava a checagem.
//
//  data_hora_checagem é a HORA REAL, de NOW() — não o horário previsto.
//  A diferença entre as duas é o atraso, e é informação clínica: o
//  sistema guarda as duas justamente para que ela exista.
//
//  O "AND status = 'pendente'" no fim impede que duas checagens
//  simultâneas sobrescrevam uma à outra.
// -------------------------------------------------------------------
$sql = "UPDATE administracoes
           SET usuario_id = ?,
               data_hora_checagem = NOW(),
               status = ?,
               justificativa = ?
         WHERE id = ? AND status = 'pendente'";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'issi', $quem_checou, $status, $justificativa, $id);
mysqli_stmt_execute($stmt);
$mudou = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

if ($mudou == 0) {
    header('Location: medicacao_turno.php?erro=ja_checada');
    exit;
}

header('Location: medicacao_turno.php?ok=' . $status);
exit;
