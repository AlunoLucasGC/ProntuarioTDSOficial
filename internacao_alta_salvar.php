<?php
// ===================================================================
//  internacao_alta_salvar.php  —  Encerra a internação
//  Módulo 2 · Sprint 4 · perfil MÉDICO
//
//  O arquivo que fecha o ciclo do paciente. Grava três coisas:
//
//      situacao        = 'alta'
//      data_alta       = agora, pelo relógio do servidor
//      alta_usuario_id = quem está logado
//
//  E não grava mais nada. Repare no que NÃO acontece aqui:
//
//    · o leito_id continua onde está — a internação guarda em que cama
//      a pessoa esteve. Zerar o leito apagaria essa informação
//    · nada é apagado
//    · o cadastro do paciente não é tocado. Ele continua no sistema,
//      e é isso que permite a recepção reinternar a mesma pessoa mais
//      tarde sem cadastrar de novo
//
//  O leito volta a ficar livre sozinho: toda consulta de leito livre
//  procura internação com situacao = 'internado'. Mudou a situação,
//  a cama saiu daquela lista.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico'));

require 'config/conexao.php';

// -------------------------------------------------------------------
//  Só por POST. A tela manda por formulário, de propósito.
//
//  Se a alta aceitasse GET, o endereço internacao_alta_salvar.php?id=1
//  daria alta em alguém só por ser visitado — e navegador, leitor de
//  link e antivírus visitam endereços sem ninguém clicar.
// -------------------------------------------------------------------
if (!isset($_POST['internacao_id'])) {
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

$internacao_id = (int) $_POST['internacao_id'];

if ($internacao_id == 0) {
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  RECONFERÊNCIA
//
//  A tela de confirmação mostrou uma fotografia do passado. Entre ela
//  aparecer e o botão ser clicado, outro médico pode ter dado alta ao
//  mesmo paciente. Então tudo se pergunta de novo, agora:
//
//    · a internação existe?
//    · ainda está em andamento?
//    · tem diagnóstico?
// -------------------------------------------------------------------
$sql  = "SELECT id, cid_id, situacao FROM internacoes
          WHERE id = ? AND ativo = 1";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
mysqli_stmt_execute($stmt);
$internacao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$internacao) {
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

if ($internacao['situacao'] == 'alta') {
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=ja_teve_alta');
    exit;
}

// -------------------------------------------------------------------
//  REGRA: não se dá alta sem diagnóstico.
//
//  A tela já desabilita o botão nesse caso, mas esconder o botão não é
//  regra — é conveniência. A regra mora aqui, no arquivo que grava.
// -------------------------------------------------------------------
if ($internacao['cid_id'] === null) {
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=sem_cid');
    exit;
}

// -------------------------------------------------------------------
//  Grava a alta.
//
//  O "AND situacao = 'internado'" no fim não é enfeite: se duas altas
//  chegarem no mesmo instante, a segunda não encontra linha para
//  atualizar e não sobrescreve a data nem a assinatura da primeira.
//
//  A data vem de NOW(), do relógio do servidor, e o autor vem da
//  sessão. Nenhum dos dois é digitado nem vem do formulário — é a
//  Regra de Ouro nº 2: todo registro tem autor e hora, e ninguém
//  escolhe os seus.
// -------------------------------------------------------------------
$quem_deu_alta = $_SESSION['usuario_id'];

$sql = "UPDATE internacoes
           SET situacao = 'alta',
               data_alta = NOW(),
               alta_usuario_id = ?
         WHERE id = ? AND situacao = 'internado' AND ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $quem_deu_alta, $internacao_id);
mysqli_stmt_execute($stmt);
$mudou = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

// -------------------------------------------------------------------
//  A ALTA ENCERRA AS PRESCRIÇÕES DESTA INTERNAÇÃO
//
//  Prescrição não sobrevive à alta: ela era uma ordem para aquela
//  estadia. Se ficasse ativa, o paciente que voltasse em seis meses
//  reapareceria com a ordem médica da internação anterior — e o
//  técnico veria doses de um episódio que já terminou.
//
//  Isso só é possível porque `prescricoes` guarda `internacao_id`
//  (migração v3). Antes, a prescrição pertencia só ao paciente e não
//  havia como saber quais encerrar.
//
//  Suspender é ativo = 0, como em todo o sistema. A prescrição continua
//  no prontuário, com as doses que foram dadas enquanto ela valia.
// -------------------------------------------------------------------
$prescricoes_encerradas = 0;

if ($mudou > 0) {

    $sql = "UPDATE prescricoes SET ativo = 0
             WHERE internacao_id = ? AND ativo = 1";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
    mysqli_stmt_execute($stmt);
    $prescricoes_encerradas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    // E as doses que ainda não foram executadas saem do turno. Mesma
    // regra do prescricao_suspender.php: só o que está PENDENTE, nunca
    // uma dose já checada.
    //
    // Dose pendente é lembrete de tarefa; dose checada é registro de
    // um ato, com hora e autor, e fica para sempre.
    $sql = "DELETE a FROM administracoes a
              JOIN prescricoes pr ON pr.id = a.prescricao_id
             WHERE pr.internacao_id = ? AND a.status = 'pendente'";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

mysqli_close($conexao);

if ($mudou == 0) {
    header('Location: internacao_alta.php?erro=ja_teve_alta');
    exit;
}

header('Location: internacao_alta.php?ok=alta&encerradas=' . $prescricoes_encerradas);
exit;
