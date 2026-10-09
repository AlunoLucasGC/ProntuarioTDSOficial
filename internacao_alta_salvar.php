<?php

require 'includes/protege.php';
require 'includes/permissao.php';

exigirPerfil(array('medico'));

require 'config/conexao.php';

if(!isset($_POST['internacao_id'])){
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

$internacao_id = (int) $_POST['internacao_id'];

if($internacao_id == 0){
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

$sql = "SELECT id, cid_id, situacao FROM internacoes
        WHERE id = ? AND ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
mysqli_stmt_execute($stmt);

$internacao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$internacao){
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=nao_encontrado');
    exit;
}

if($internacao['situacao'] == 'alta'){
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=ja_teve_alta');
    exit;
}

if($internacao['cid_id'] === null){
    mysqli_close($conexao);
    header('Location: internacao_alta.php?erro=sem_cid');
    exit;
}


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

$prescricoes_encerradas = 0;

if($mudou > 0 ){
    $sql = "UPDATE prescricoes SET ativo = 0
            WHERE internado_id = ? AND ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
mysqli_stmt_execute($stmt);

$prescricoes_encerradas = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

//Apagar doses pendentes 
$sql = "DELETE a FROM administracoes a 
        JOIN prescricoes pr ON pr.id = a.prescricao_id
        WHERE pr.internacao_id = ? AND a.status = 'pendentes'";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);




}

?>