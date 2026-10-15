<?php

// Passo 1 - Conectar no Banco
require 'config/conexao.php';
require 'includes/funcoes2.php';

//Passo 2 - Escrever a Consulta no banco para exibir na tela 
//O Texto da consulta fica guardada na variável, para ser usada depois 

$sql = "SELECT id, nome, data_nascimento, sexo, ativo 
        FROM pacientes 
        ORDER BY nome";


//Passo 3 - Mandar pedido, enviar a consulta para o banco
//$resultado é o monte de linhas que voltou 
// Não dá para imprimir $resultado direto: as linhas saem uma por vez

$resultado = mysqli_query($conexao, $sql);

// Passo 4 - Perguntar quantas linhas vieram 
//Banco nos devolve quantas linhas 

$quantos = mysqli_num_rows($resultado);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Pacientes</title>
    <link rel="stylesheet" href="assets/vendor/css/core.css">
</head>

<body class="bg-body-tertiary">
    <div class="container- py-4">
    <h1 class="h3 mb-1">
        Listar Pacientes 
    </h1>
    <p class="text-secondary mb-4"> O <strong>R</strong> do CRUD - Ler e Mostrar</p>
    <div class="card mb-4">
        <div class="card-body">
        <h2 class="h6 text-primary mb-2">O que esta página faz</h2>
        <p class="mb-2 ">Lê a tabela <code>Pacientes</code> e mostra na tela.</p>
        
        <p class="mb-0 small text-secondary ">
            <strong>Passo 1</strong> Conectar no banco.
            <strong>Passo 2</strong> Escreve o SELECT.
            <strong>Passo 3</strong> Manda o SELECT para o banco.
            <strong>Passo 4</strong> Pergunta quantas linhas vieram.
            <strong>Passo 5</strong> o <code> while </code> imprime uma linha por paciente.
        </p>
        </div>
</div>
<div class="card mb-4">
    <div class="card-body">
    <h2 class="h6 mb-2">Este foi o SELECT que o PHP mandou para o banco:</h2>
    <pre class="bg-body-tertiary border rounded p-3 mb-0"><?php echo $sql ?></pre>
    </div>
</div>

    <?php

    // -------------------------------------------------------------------
    //  RECADO DA PÁGINA ANTERIOR
    //
    //  As outras telas terminam com header('Location: ...?ok=alguma_coisa')
    //  e esse pedaço da URL chega aqui dentro de $_GET. É assim que uma
    //  página avisa a outra que deu certo — sem guardar nada em lugar
    //  nenhum, só pela barra de endereço.
    //
    //  A URL traz só uma palavra-código. A frase que a pessoa lê está
    //  escrita AQUI, nesta lista. Assim ninguém consegue fazer a tela
    //  exibir o texto que quiser trocando a URL na mão.
    // -------------------------------------------------------------------

    $frases = array(
        'cadastrado' => 'Paciente cadastrado',
        'atualizado' => 'Cadastro atualizado',
        'inativado'  => 'Cadastro inativo',
        'reativado'  => 'Cadastro reativado',
        'campos'     => 'Faltou preencher nome, nascimento ou sexo',
        'nao_encontrado' => 'Esse paciente não existe no banco.'
    );

    if (isset($_GET['ok']) && isset($frases[$_GET['ok']])) {
        echo '<p><b> Deu certo: </b> ' . $frases[$_GET['ok']] . '</p>';
    }
    if (isset($_GET['erro']) && isset($frases[$_GET['erro']])) {
        echo '<p><b> Deu erro: </b> ' . $frases[$_GET['erro']] . '</p>';
    }

    ?>
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="paciente_form2.php" class="btn btn-primary">Cadastrar novo paciente</a>
         <a href="funcoes2.php" class="btn btn-outline-secondary">Ver as functions que essa tabela usa</a>
    </div>

<div class="card">
    <div class="card-header">
        <strong><?php echo $quantos;?></strong>
        paciente(s) na tabela.
        <span class="text-primary small">
            Esse número veio do <code>mysqli_num_rows</code>, não contado a mão.
        </span>
    </div>

    <div class="table-responsive">
    <table class="table table-hover aling-middle mb-0">
        <tr> <!--Significa Table Row, Linha da tabela -->
            <th>Id</th>
            <th>Nome</th> <!--Significa Table header, célula do cabeçalho -->
            <th>Nascimento</th>
            <th>Idade</th>
            <th>Sexo</th>
            <th>Ativo</th>
            <th>Ações</th>
        </tr>

        <?php
        //Passo 5 - 
        //mysqli_fetch_assoc() tira uma linha da pilha e devolve num array.
        //Na próxima volta, tira a seguinte. Quando a pilha acaba, devolve nulo
        //o while() entende o falso e o laço para sozinho.


        // Dentro do laço, $p é UM paciente. $p['nome'] é o nome dele.
        while ($p = mysqli_fetch_assoc($resultado)) {
        ?>

            <tr>
                <td><?php echo $p['id']; ?></td> <!-- Table Data, dados da tabela -->
                <!--htmlspecialchars() é obrigatório em tudo que vem do banco
                se alguém cadastrar um nome com sinal de menor(<br>), sem ele o
                navegador tentaria entender aquilo como tag HTML -->
                <td><?php echo htmlspecialchars($p['nome']); ?></td>
                <td><?php echo (dataNaTela($p['data_nascimento'])); ?></td>
                <td><?php echo (calcularIdade($p['data_nascimento'])); ?></td>
                <td><?php echo htmlspecialchars(nomeDoSexo($p['sexo'])); ?></td>
                <td><?php echo ($p['ativo'] == 1 ? 'Sim' : 'Não'); ?></td>
                <td>
                    <a href="paciente_form2.php?id=<?php echo $p['id']; ?>"> Editar </a>
                    <a href="paciente_inativar2.php= <?php echo $p['id']; ?>">
                        <?php echo ($p['ativo'] == 1 ? 'Inativar' : 'Reativar'); ?>
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>
    </div>
</div>
    </div>
</body>

</html>