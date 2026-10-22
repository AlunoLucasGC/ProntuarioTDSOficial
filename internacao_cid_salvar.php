<?php

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico'));
require 'config/conexao.php';

if(!isset($_POST['internacao_id']) || !isset($_POST['cid_id'])){
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

$internacao_id= (int) $_POST['internacao_id'];
$cid_id= (int) $_POST['cid_id'];

if($internacao_id == 0 || $cid_id == 0){
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

$sql = "SELECT id FROM internacoes 
        WHERE id = ? AND situacao = 'internado' AND ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $cid_id);
mysqli_stmt_execute($stmt);
$cid = mysqli_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if(!$cid){
    mysqli_close($conexao);
    header('Location: internacao_alta.php');
    exit;
}

$sql = "UPDATE internacaoes SET cid_id = ? WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $cid_id, $internacao_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);
 header('Location: internacao_alta.php?ok=diagnostico');
    exit;



?>