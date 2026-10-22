<?php
// ===================================================================
//  internacao_cid_salvar.php  —  Grava o diagnóstico da internação
//  Módulo 2 · Sprint 4 · perfil MÉDICO
//
//  Não desenha nada. Confere, grava e devolve para a tela.
//
//  A verificação de perfil se repete aqui, e não é exagero: a tela
//  internacao_alta.php aparece só para o médico, mas ninguém precisa
//  da tela para enviar um formulário. Sem esta linha, qualquer pessoa
//  logada poderia mudar o diagnóstico de uma internação.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico'));

require 'config/conexao.php';

// -------------------------------------------------------------------
//  O que chegou do formulário
// -------------------------------------------------------------------
if (!isset($_POST['internacao_id']) || !isset($_POST['cid_id'])) {
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

$internacao_id = (int) $_POST['internacao_id'];
$cid_id        = (int) $_POST['cid_id'];

if ($internacao_id == 0 || $cid_id == 0) {
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  A internação existe e está em andamento?
//
//  Diagnóstico de internação encerrada não se muda por esta tela: o
//  registro já foi assinado por quem deu a alta.
// -------------------------------------------------------------------
$sql  = "SELECT id FROM internacoes
          WHERE id = ? AND situacao = 'internado' AND ativo = 1";
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

// -------------------------------------------------------------------
//  O CID que chegou existe e está ativo?
//
//  O <select> da tela só oferecia os ativos — mas o POST pode chegar
//  sem passar por ele. É a mesma conferência que o leito_salvar.php
//  faz com o setor: valor escolhido em lista se reconfere no banco.
// -------------------------------------------------------------------
$sql  = "SELECT id FROM cids WHERE id = ? AND ativo = 1";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $cid_id);
mysqli_stmt_execute($stmt);
$cid = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$cid) {
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=cid_invalido');
    exit;
}

// -------------------------------------------------------------------
//  Grava
// -------------------------------------------------------------------
$sql  = "UPDATE internacoes SET cid_id = ? WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $cid_id, $internacao_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

header('Location: internacao_alta.php?ok=diagnostico');
exit;

?>