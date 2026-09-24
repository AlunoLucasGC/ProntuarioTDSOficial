<?php

// Passo 1 - Conectar no Banco
require 'config/conexao.php';

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
</head>

<body>
    <h1>
        Listar Pacientes - o R do CRUD
    </h1>
    <fieldset>
        <legend>
            <b>O que esta página faz</b>
        </legend>
        <p>
            Lê a tabela <code>Pacientes</code> e mostra na tela.
        </p>
        <p>
            <b>Passo 1</b> Conectar no banco.
            <b>Passo 2</b> Escreve o SELECT.
            <b>Passo 3</b> Manda o SELECT para o banco.
            <b>Passo 4</b> Pergunta quantas linhas vieram.
            <b>Passo 5</b> o <code> while </code> imprime uma linha por paciente.
        </p>
    </fieldset>
    <p><b>Este foi o SELECT que o PHP mandou para o banco:</b></p>
    <pre><?php echo $sql ?></pre>

    <table border="1" cellpadding="4" cellspacing="0">
        <tr> <!--Significa Table Row, Linha da tabela -->
            <th>Id</th>
            <th>Nome</th> <!--Significa Table header, célula do cabeçalho -->
            <th>Nascimento</th>
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
                <td><?php echo $p['data_nascimento']; ?></td>
                <td><?php echo $p['sexo']; ?></td>
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


</body>

</html>