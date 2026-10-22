<?php

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));
require 'config/conexao.php';

//isset: verifica se existe
//"!" negação
// "!isset": campo não foi enviado ou não foi definido
// || = operador lógico OU
if(isset($_POST['paciente_id']) || !isset($_POST['leito_id'])){
    header('Location: internacao_moviementar.php');
    exit;
}

$paciente_id = (int) $_POST['paciente_id'];
$leito_id = (int) $_POST['leito_id'];

$sql = "SELECT id FROM PACIENTES WHERE id = ? AND ativo = 1";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if(!$paciente){
    header('Location: internacao_movimentar.php?erro=nao_encontrado');
    exit;
}


$sql = "SELECT id FROM leitos WHERE id = ? AND ativo = 1";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $leito_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$leito = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if(!$leito){
    header('Location: internacao_movimentar.php?erro=leito_invalido');
    exit;
}


$sql = "SELECT id FROM internacoes
 WHERE leito_id = ? AND situacao = 'internado' AND ativo = 1";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $leito_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$ocupado = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if(!$ocupado){
    header('Location: internacao_movimentar.php?erro=leito_ocupado');
    exit;
}


$quem_admitiu = $_SESSION['usuario_id'];

$sql = "INSERT INTO internacoes (paciente_id, leito_id, situacao, data_admissao, admitido_por, ativo) 
VALUES (?,?,'internado', NOW(), ? , 1)";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'iii', $paciente_id, $leito_id, $quem_admitiu);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

    header('Location: internacao_movimentar.php?erro=leito_invalido');
    exit;




?>

