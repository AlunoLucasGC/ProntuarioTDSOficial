<?php

require 'config/conexao.php';

//Passo 1 - Identificar o paciente

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id == 0) {
    header('Location: paciente_listar2.php?erro=nao_encontrado');
    exit;
}

//Passo 2 - Descobrir como o cadastrado está

$sql = "SELECT ativo FROM pacientes WHERE id = ?";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_close($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$paciente) {
    header('Location: paciente_listar.php?erro=nao_encontrado');
    exit;
}

$novo_valor = ($paciente['ativo'] == 1 ? 0 : 1);

$sql = "UPDATE pacientes SET ativo = ? WHERE id = ? ";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $novo_valor, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conexao);

$aviso = ($novo_valor == 0 ? 'inativo' : 'reativado');

header('Location: paciente_listar2.php?ok=' . $aviso);
exit;

?>