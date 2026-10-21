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



?>

