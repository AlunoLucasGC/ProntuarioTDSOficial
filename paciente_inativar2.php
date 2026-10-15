<?php
// ===================================================================
//  paciente_inativar2.php   —   versão de estudo, sem CSS
//
//  O "D" do CRUD: D de DELETE, apagar.
//
//  SÓ QUE AQUI NÃO SE APAGA NADA. Este arquivo desliga o cadastro
//  em vez de apagar. É o que se chama de EXCLUSÃO LÓGICA: a linha
//  continua no banco, com uma coluna dizendo que ela não está em uso.
//
//      ativo = 1  ->  cadastro em uso
//      ativo = 0  ->  cadastro desligado
//
//  O DELETE de verdade seria assim:
//
//      DELETE FROM pacientes WHERE id = ?
//
//  E não se usa ele aqui por dois motivos:
//
//  1. O paciente está amarrado a sinais vitais, anotações de
//     enfermagem, evoluções e checagens de medicação. Apagar o
//     paciente apagaria o prontuário — e prontuário é documento legal.
//
//  2. DELETE não tem desfazer. UPDATE ativo = 0 tem: é só voltar
//     para 1. Este mesmo arquivo faz as duas coisas.
//
//  Esta página também não tem tela. Ela recebe um id, vira a chave,
//  e manda o navegador de volta para a lista.
// ===================================================================


require 'config/conexao.php';


// PASSO 1 — DE QUAL PACIENTE ESTAMOS FALANDO?
//
// O id veio na URL, pelo link da lista:
//     paciente_inativar2.php?id=7
//
// (int) força a virar número inteiro. Se vier lixo, vira 0.
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id == 0) {
    header('Location: paciente_listar2.php?erro=nao_encontrado');
    exit;
}


// PASSO 2 — DESCOBRIR COMO ESSE CADASTRO ESTÁ AGORA.
//
// Antes de virar a chave é preciso saber para que lado virar. Se
// está ligado, desliga; se está desligado, liga.
$sql  = "SELECT ativo FROM pacientes WHERE id = ?";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente  = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

// Pediram um id que não existe no banco.
if (!$paciente) {
    header('Location: paciente_listar2.php?erro=nao_encontrado');
    exit;
}


// PASSO 3 — VIRAR A CHAVE.
//
// Está 1? passa a ser 0. Está 0? passa a ser 1.
// É a linha que faz este arquivo servir para inativar E para reativar.
$novo_valor = ($paciente['ativo'] == 1 ? 0 : 1);


// PASSO 4 — GRAVAR.
//
// Repare que isto é um UPDATE, não um DELETE. A linha do paciente
// continua inteira no banco; só a coluna ativo muda de valor.
//
// E de novo: o WHERE é o que importa. Sem ele, este comando
// desligaria TODOS os pacientes da tabela de uma vez.
$sql  = "UPDATE pacientes SET ativo = ? WHERE id = ?";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $novo_valor, $id);   // dois inteiros
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);


// PASSO 5 — VOLTAR PARA A LISTA COM O RECADO.
mysqli_close($conexao);

$aviso = ($novo_valor == 0 ? 'inativado' : 'reativado');

header('Location: paciente_listar2.php?ok=' . $aviso);
exit;
