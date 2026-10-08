<?php
// ===================================================================
//  medicacao_turno.php  —  Medicações do turno
//  Módulo 5 · Sprint 7 · perfil TÉCNICO
//
//  A tela mais usada do sistema, e o motivo de ele existir.
//
//  Três blocos, nesta ordem — que é a ordem da urgência:
//
//    1. ATRASADAS       horário já passou e ninguém checou
//    2. DE HOJE         as próximas, ainda no horário
//    3. SE NECESSÁRIO   sem hora marcada, o técnico dá quando precisa
//
//  Checar é dizer uma de duas coisas: administrado, ou NÃO administrado
//  com justificativa. Não existe terceira opção, e não existe deixar em
//  branco: dose que não foi dada é o evento clínico mais importante do
//  plantão, e some do prontuário se ninguém registrar.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/prescricoes.php';
require 'includes/doses.php';

// -------------------------------------------------------------------
//  A busca por nome (convenção 19)
// -------------------------------------------------------------------
// -------------------------------------------------------------------
//  ANTES DE MOSTRAR, COMPLETA AS DOSES QUE FALTAM
//
//  ┌──────────────────────────────────────────────────────────────┐
//  │  ESTA É A ÚNICA TELA DO SISTEMA EM QUE UM GET GRAVA.          │
//  └──────────────────────────────────────────────────────────────┘
//
//  Isso contraria a convenção 16 do projeto — GET mostra, POST grava —
//  e a exceção foi decidida de propósito. O motivo:
//
//  As doses eram geradas uma vez, ao prescrever, para sete dias. Nada
//  as repunha. Paciente internado mais de uma semana PARAVA DE TER
//  DOSE, em silêncio: a prescrição seguia ativa e a tela não a mostrava
//  mais. Num prontuário, isso é medicação que desaparece de quem tinha
//  que administrá-la.
//
//  A alternativa que respeitava a convenção era um botão "gerar doses".
//  Foi recusada: quem não clicasse continuaria sem ver, e no plantão
//  ninguém deve precisar de um passo extra para descobrir o que fazer.
//
//  O QUE FAZ A EXCEÇÃO SER ACEITÁVEL
//
//    é idempotente     rodar dez vezes cria as doses uma vez
//    é limitada        só para a frente, e só até diasParaGerar()
//    é conservadora    cria apenas linhas 'pendente'; nunca toca dose
//                      já checada, nunca apaga, nunca corrige
//    para sozinha      prescrição suspensa ou paciente com alta não
//                      geram nada
//
//  Vale como aula: regra boa tem exceção documentada; regra que se
//  contorna em silêncio deixa de ser regra.
// -------------------------------------------------------------------
reporDosesDoTurno($conexao);

$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}
$curinga = '%' . $busca . '%';

// -------------------------------------------------------------------
//  AS DOSES COM HORA MARCADA
//
//  Traz o pendente de hoje e de antes. O que é "atrasado" e o que é
//  "de hoje" se decide no PHP, comparando com a hora de agora — assim
//  a consulta é uma só.
//
//  Só entra dose de prescrição ATIVA e de paciente INTERNADO: ordem
//  suspensa não se executa, e paciente que teve alta não recebe dose.
// -------------------------------------------------------------------
$sql = "SELECT a.id, a.horario_previsto, a.status,
               pr.id AS prescricao_id, pr.dose, pr.via, pr.frequencia,
               COALESCE(pr.observacao, '') AS observacao,
               m.nome AS medicamento,
               COALESCE(m.apresentacao, '') AS apresentacao,
               p.id AS paciente_id, p.nome AS paciente,
               COALESCE(p.alergias, '') AS alergias,
               COALESCE(l.identificacao, '') AS leito
        FROM administracoes a
        JOIN prescricoes  pr ON pr.id = a.prescricao_id AND pr.ativo = 1
        JOIN medicamentos m  ON m.id = pr.medicamento_id
        JOIN pacientes    p  ON p.id = pr.paciente_id
        -- O vínculo é pela INTERNAÇÃO da prescrição, não pelo paciente.
        -- Assim a dose de uma estadia anterior não aparece no turno de
        -- quem recebeu alta e voltou.
        JOIN internacoes  i  ON i.id = pr.internacao_id
                            AND i.situacao = 'internado'
                            AND i.ativo = 1
        LEFT JOIN leitos  l  ON l.id = i.leito_id
        WHERE a.status = 'pendente'
          -- A JANELA DO TURNO, não o dia do calendário.
          --
          -- Sem limite para trás: dose atrasada continua atrasada,
          -- seja de hoje ou da semana passada, até alguém checá-la.
          -- Para a frente, até o fim do plantão — ver horasDoTurno().
          AND a.horario_previsto <= DATE_ADD(NOW(), INTERVAL ? HOUR)
          AND p.nome LIKE ?
        ORDER BY a.horario_previsto, l.identificacao";

$horas = horasDoTurno();

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'is', $horas, $curinga);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Separa em atrasadas e a dar. O corte é AGORA: o que já venceu está
// atrasado, o que ainda não venceu está no horário.
$atrasadas = array();
$de_hoje   = array();
$agora     = date('Y-m-d H:i:s');

while ($linha = mysqli_fetch_assoc($res)) {
    if ($linha['horario_previsto'] < $agora) {
        $atrasadas[] = $linha;
    } else {
        $de_hoje[] = $linha;
    }
}

mysqli_stmt_close($stmt);

// -------------------------------------------------------------------
//  AS PRESCRIÇÕES "SE NECESSÁRIO"
//
//  Elas não têm linha em `administracoes` esperando — não há hora
//  marcada. Aparecem para o técnico poder registrar no momento em que
//  administrar, e a linha nasce já checada.
// -------------------------------------------------------------------
$sql = "SELECT pr.id AS prescricao_id, pr.dose, pr.via, pr.frequencia,
               COALESCE(pr.observacao, '') AS observacao,
               m.nome AS medicamento,
               COALESCE(m.apresentacao, '') AS apresentacao,
               p.id AS paciente_id, p.nome AS paciente,
               COALESCE(p.alergias, '') AS alergias,
               COALESCE(l.identificacao, '') AS leito,
               (SELECT COUNT(*) FROM administracoes
                 WHERE prescricao_id = pr.id
                   AND DATE(horario_previsto) = CURDATE()) AS dadas_hoje
        FROM prescricoes pr
        JOIN medicamentos m ON m.id = pr.medicamento_id
        JOIN pacientes    p ON p.id = pr.paciente_id
        JOIN internacoes  i ON i.id = pr.internacao_id
                           AND i.situacao = 'internado'
                           AND i.ativo = 1
        LEFT JOIN leitos  l ON l.id = i.leito_id
        WHERE pr.ativo = 1
          AND pr.horarios IS NULL
          AND pr.data_inicio <= CURDATE()
          AND (pr.data_fim IS NULL OR pr.data_fim >= CURDATE())
          AND p.nome LIKE ?
        ORDER BY l.identificacao, m.nome";

$stmt_sos = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt_sos, 's', $curinga);
mysqli_stmt_execute($stmt_sos);
$res_sos = mysqli_stmt_get_result($stmt_sos);

$se_necessario = array();
while ($linha = mysqli_fetch_assoc($res_sos)) {
    $se_necessario[] = $linha;
}
mysqli_stmt_close($stmt_sos);

// -------------------------------------------------------------------
//  O QUE VEM DEPOIS DA JANELA
//
//  A tela mostra só o que já venceu ou vence até o fim do plantão — é
//  a pergunta "o que eu tenho que fazer agora?". Mas quem abre a
//  ficha do paciente vê "3 pendentes" e vem aqui procurar as três.
//  Se a tela não disser nada sobre o que ficou fora da janela, ela
//  parece estar escondendo dose.
//
//  Então esta consulta roda SEMPRE, e responde duas coisas: quantas
//  doses ficaram depois do plantão, e quando é a primeira delas.
//
//  Ela é a única da tela SEM o filtro de janela — de propósito.
// -------------------------------------------------------------------
$proxima  = null;
$adiante  = 0;

{

    $sql = "SELECT a.horario_previsto, m.nome AS medicamento, p.nome AS paciente,
                   COALESCE(l.identificacao, '') AS leito
            FROM administracoes a
            JOIN prescricoes  pr ON pr.id = a.prescricao_id AND pr.ativo = 1
            JOIN medicamentos m  ON m.id = pr.medicamento_id
            JOIN internacoes  i  ON i.id = pr.internacao_id
                                AND i.situacao = 'internado'
                                AND i.ativo = 1
            JOIN pacientes    p  ON p.id = i.paciente_id
            LEFT JOIN leitos  l  ON l.id = i.leito_id
            WHERE a.status = 'pendente'
              AND a.horario_previsto > DATE_ADD(NOW(), INTERVAL ? HOUR)
              AND p.nome LIKE ?
            ORDER BY a.horario_previsto";

    $stmt_prox = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt_prox, 'is', $horas, $curinga);
    mysqli_stmt_execute($stmt_prox);
    $res_prox = mysqli_stmt_get_result($stmt_prox);

    // A primeira linha é a próxima dose; o total é quantas ficaram
    // para depois do plantão.
    while ($linha = mysqli_fetch_assoc($res_prox)) {
        if ($proxima === null) { $proxima = $linha; }
        $adiante = $adiante + 1;
    }

    mysqli_stmt_close($stmt_prox);
}

$titulo    = 'Medicações do turno';
$subtitulo = 'Checagem de administração';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'administrado'     => 'Dose registrada como administrada.',
    'nao_administrado' => 'Dose registrada como NÃO administrada, com a justificativa.'
);

$avisos_erro = array(
    'nao_encontrado' => 'Dose não encontrada.',
    'ja_checada'     => 'Essa dose já foi checada. Atualize a tela.',
    'sem_justificativa' => 'Para registrar como não administrada, a justificativa é obrigatória.',
    'status_invalido'   => 'Situação inválida.',
    'nao_internado'     => 'Esse paciente não está internado.',
    'suspensa'          => 'Essa prescrição foi suspensa pelo médico.'
);

if (isset($_GET['ok']) && isset($avisos_ok[$_GET['ok']])) {
?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-check-circle me-2"></i>
    <div><?php echo $avisos_ok[$_GET['ok']]; ?></div>
  </div>
<?php
}

if (isset($_GET['erro']) && isset($avisos_erro[$_GET['erro']])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div><?php echo $avisos_erro[$_GET['erro']]; ?></div>
  </div>
<?php
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Doses a checar</h5>
    <small class="text-body-secondary">
      <?php if (count($atrasadas) > 0) { ?>
        <span class="valor-alterado"><?php echo count($atrasadas); ?> atrasada<?php echo (count($atrasadas) == 1 ? '' : 's'); ?></span>
        ·
      <?php } ?>
      <?php echo count($de_hoje); ?> no horário
      <?php if (count($se_necessario) > 0) { ?>
        · <?php echo count($se_necessario); ?> se necessário
      <?php } ?>
    </small>
  </div>
  <small class="text-body-secondary"><?php echo date('d/m/Y'); ?></small>
</div>

<form method="get" action="medicacao_turno.php" class="mb-3">
  <div class="input-group">
    <input type="text" name="busca" class="form-control"
           placeholder="Buscar por nome…"
           value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="medicacao_turno.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="mb-3">
  <small class="text-body-secondary">
    <span class="badge bg-label-danger">Atrasada</span> horário já passou
    &nbsp;·&nbsp;
    <span class="badge bg-label-warning">No horário</span> a dar até o fim do plantão
    &nbsp;·&nbsp;
    <span class="badge bg-label-info">Se necessário</span> sem hora marcada
  </small>
</div>

<?php
// ===================================================================
//  Desenha um bloco de doses com hora marcada.
//  A mesma função serve para atrasadas e para as de hoje — muda só o
//  título e a cor do selo.
// ===================================================================
function desenharDoses($doses, $titulo_bloco, $cor_selo, $rotulo_selo, $vazio)
{
?>
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><?php echo $titulo_bloco; ?></h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Horário</th>
            <th>Paciente</th>
            <th>Medicamento</th>
            <th style="width:34%">Checagem</th>
          </tr>
        </thead>
        <tbody>

        <?php
        if (count($doses) == 0) {
        ?>
          <tr><td colspan="4" class="text-center text-body-secondary py-4">
            <?php echo $vazio; ?>
          </td></tr>
        <?php
        }

        foreach ($doses as $d) {
        ?>
          <tr>
            <td class="text-nowrap">
              <strong><?php echo date('H:i', strtotime($d['horario_previsto'])); ?></strong><br>
              <small class="text-body-secondary">
                <?php echo date('d/m', strtotime($d['horario_previsto'])); ?>
              </small><br>
              <span class="badge <?php echo $cor_selo; ?>"><?php echo $rotulo_selo; ?></span>
            </td>

            <td>
              <?php if ($d['leito'] != '') { ?>
                <strong><?php echo htmlspecialchars($d['leito']); ?></strong> ·
              <?php } ?>
              <?php echo htmlspecialchars($d['paciente']); ?>
              <?php
              // REGRA DE OURO Nº 4 — e aqui ela é vital: esta é a tela
              // onde alguém está com o medicamento na mão.
              $texto_alergia = trim($d['alergias']);
              $nega = negaAlergias($texto_alergia);
              if (!$nega) {
              ?>
                <br><span class="valor-alterado small">
                  <i class="icon-base bx bx-error-circle"></i>
                  <?php echo htmlspecialchars($texto_alergia); ?>
                </span>
              <?php } ?>
            </td>

            <td>
              <strong><?php echo htmlspecialchars($d['medicamento']); ?></strong><br>
              <?php echo htmlspecialchars($d['dose']); ?>
              · <?php echo htmlspecialchars($d['via']); ?>
              · <?php echo htmlspecialchars($d['frequencia']); ?>
              <?php if ($d['observacao'] != '') { ?>
                <br><small class="text-body-secondary">
                  <?php echo htmlspecialchars($d['observacao']); ?>
                </small>
              <?php } ?>
            </td>

            <td>
              <!-- Dois botões, dois caminhos. O "Administrado" grava
                   direto. O "Não administrado" leva para a tela que
                   pede a justificativa, porque ela é obrigatória. -->
              <form action="medicacao_checar.php" method="post" class="d-flex gap-2 flex-wrap">
                <input type="hidden" name="administracao_id" value="<?php echo $d['id']; ?>">
                <input type="hidden" name="status" value="administrado">
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                  <i class="icon-base bx bx-check me-1"></i> Administrado
                </button>
              </form>
              <a href="medicacao_checar.php?administracao_id=<?php echo $d['id']; ?>"
                 class="btn btn-sm btn-outline-danger text-nowrap mt-2">
                Não administrado…
              </a>
            </td>
          </tr>
        <?php } ?>

        </tbody>
      </table>
    </div>
  </div>
<?php
}

// -------------------------------------------------------------------
//  1. ATRASADAS — só aparece o bloco se houver
// -------------------------------------------------------------------
if (count($atrasadas) > 0) {
    desenharDoses($atrasadas, 'Atrasadas', 'bg-label-danger', 'Atrasada', '');
}

// -------------------------------------------------------------------
//  2. DE HOJE
// -------------------------------------------------------------------
// -------------------------------------------------------------------
//  quandoLegivel()
//  "amanhã às 04:00" lê melhor que "20/08 às 04:00" quando é amanhã
//  mesmo. Acima disso, a data ajuda mais que o apelido.
// -------------------------------------------------------------------
function quandoLegivel($horario)
{
    $quando = strtotime($horario);

    if (date('Y-m-d', $quando) == date('Y-m-d')) {
        return 'hoje às ' . date('H:i', $quando);
    }

    if (date('Y-m-d', $quando) == date('Y-m-d', strtotime('+1 day'))) {
        return 'amanhã às ' . date('H:i', $quando);
    }

    return date('d/m', $quando) . ' às ' . date('H:i', $quando);
}

// A mensagem do bloco vazio muda conforme o que existe adiante.
$vazio = 'Nada para dar até o fim do plantão.';

if ($proxima) {

    $vazio = 'Nada até o fim do plantão. A próxima é '
           . quandoLegivel($proxima['horario_previsto'])
           . ' — ' . htmlspecialchars($proxima['medicamento'])
           . ', ' . htmlspecialchars($proxima['paciente'])
           . ($proxima['leito'] != '' ? ' (' . htmlspecialchars($proxima['leito']) . ')' : '')
           . '.';

} else if (count($se_necessario) > 0) {
    $vazio = 'Nada com hora marcada. Só as do "se necessário", abaixo.';
} else if ($busca != '') {
    $vazio = 'Nenhuma dose pendente para esse nome.';
}

desenharDoses($de_hoje, 'No horário', 'bg-label-warning', 'No horário', $vazio);

// -------------------------------------------------------------------
//  O QUE FICOU PARA DEPOIS DO PLANTÃO
//
//  Uma linha só, discreta, e só quando há algo adiante E a lista não
//  está vazia (com a lista vazia, o próprio estado vazio já disse).
//
//  Por que não um quarto bloco com essas doses: a tela já foi dividida
//  uma vez justamente por ter informação demais. Uma frase responde
//  "e as outras?" sem devolver o problema.
// -------------------------------------------------------------------
if ($adiante > 0 && count($de_hoje) > 0) {
?>
  <p class="text-body-secondary small mt-n2 mb-4">
    <i class="icon-base bx bx-time-five me-1"></i>
    Mais <?php echo $adiante; ?>
    <?php echo ($adiante == 1 ? 'dose' : 'doses'); ?>
    depois do plantão — a próxima
    <?php echo quandoLegivel($proxima['horario_previsto']); ?>,
    <?php echo htmlspecialchars($proxima['medicamento']); ?>
    para <?php echo htmlspecialchars($proxima['paciente']); ?>.
  </p>
<?php
}


// -------------------------------------------------------------------
//  3. SE NECESSÁRIO
// -------------------------------------------------------------------
if (count($se_necessario) > 0) {
?>
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Se necessário</h5>
      <small class="text-body-secondary">
        Sem hora marcada. Registre no momento em que administrar.
      </small>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Medicamento</th>
            <th>Hoje</th>
            <th style="width:26%">Registrar</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($se_necessario as $s) { ?>
          <tr>
            <td>
              <?php if ($s['leito'] != '') { ?>
                <strong><?php echo htmlspecialchars($s['leito']); ?></strong> ·
              <?php } ?>
              <?php echo htmlspecialchars($s['paciente']); ?>
              <?php
              $texto_alergia = trim($s['alergias']);
              $nega = negaAlergias($texto_alergia);
              if (!$nega) {
              ?>
                <br><span class="valor-alterado small">
                  <i class="icon-base bx bx-error-circle"></i>
                  <?php echo htmlspecialchars($texto_alergia); ?>
                </span>
              <?php } ?>
            </td>
            <td>
              <strong><?php echo htmlspecialchars($s['medicamento']); ?></strong><br>
              <?php echo htmlspecialchars($s['dose']); ?>
              · <?php echo htmlspecialchars($s['via']); ?>
              · <?php echo htmlspecialchars($s['frequencia']); ?>
              <?php if ($s['observacao'] != '') { ?>
                <br><small class="text-body-secondary">
                  <?php echo htmlspecialchars($s['observacao']); ?>
                </small>
              <?php } ?>
            </td>
            <td class="text-nowrap">
              <?php if ($s['dadas_hoje'] == 0) { ?>
                <span class="text-body-secondary">nenhuma vez</span>
              <?php } else { ?>
                <span class="badge bg-label-success">
                  <?php echo $s['dadas_hoje']; ?>x hoje
                </span>
              <?php } ?>
            </td>
            <td>
              <form action="medicacao_checar.php" method="post" class="d-flex gap-2">
                <input type="hidden" name="prescricao_id" value="<?php echo $s['prescricao_id']; ?>">
                <input type="hidden" name="status" value="administrado">
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                  <i class="icon-base bx bx-check me-1"></i> Administrei agora
                </button>
              </form>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
}
?>

<?php
mysqli_close($conexao);
require 'includes/rodape.php';
?>
