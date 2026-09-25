<?php
require 'config/conexao.php';



//PASSO 1: Começar com tudo zerado
//Se for um cadastro novo, é isto que vai aparecer nos campos 

$id         = 0;
$nome       = '';
$nascimento = '';
$sexo       = '';
$telefone   = '';
$editando   = false;


//Passo 2 - VEIO ID NA URL?
//paciente_form2.php?id=7 -> $_GET['id'] vale 7.
//paciente_form2.php      -> $_GET['id'] não existe.
if (isset($_GET['id'])) {
    //(int) força o valor a virar número inteiro. Se alguém digitar
    // >id=abc na barra de endereço, isso vira - e não quebra nada. 
    $id = (int) $_GET['id'];

    //Passo 3 - BUSCAR ESSE PACIENTE NO BANCO
    //
    // A interrogação é um buraco na consulta. O valor NÃO é colado
    // dentro do texto do SQL: ele é entregue depois, separado, pelo
    // bind_param. É assim que se evita SQL injection.
    // COALESCE troca nulo por texto vazio ainda no banco. Telefone
    // aceita nulo, e nulo dentro de htmlspecialchars() imprime aviso
    // na tela. Resolvido aqui, o formulário fica limpo.

    $sql  = "SELECT id, nome, data_nascimento, sexo,
                    COALESCE(telefone, '') AS telefone
             FROM pacientes
             WHERE id = ?";

    $stmt = mysqli_prepare($conexao, $sql); //Prepara a consulta
    mysqli_stmt_bind_param($stmt, 'i', $id); // 'i' = o buraco é um inteiro
    mysqli_stmt_execute($stmt); // Vai para o banco

    $resultado = mysqli_stmt_get_result($stmt);
    $paciente = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    //Pediram um id que não existe? Volta para a lista com o recado.
    if (!$paciente) {
        header('Location: paciente_listar2.php?erro=nao_encontrado');
    }

    //PASSO 4 - Jogar o que veio do banco nas variáveis
    //São as mesma variáveis do passo 1. A diferença é que agora
    //Elas tem conteúdo - e é esse conteúdo que aparece nos campos

    $nome           = $paciente['nome'];
    $nascimento     = $paciente['data_nascimento'];
    $sexo           = $paciente['sexo'];
    $telefone       = $paciente['telefone'];
    $editando       = true;
}

mysqli_close($conexao);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($editando ? 'Editar Paciente' : 'Cadastrar paciente'); ?></title>
</head>

<body>
    <h1>
        <?php
        // O título da tela muda junto. Quem está usando precisa saber se
        // está criando gente nova ou corrigindo alguém que já existe.
        echo ($editando ? 'Editar paciente — o U do CRUD' : 'Cadastrar paciente — o C do CRUD');
        ?>
    </h1>

    <fieldset>
        <legend><b>O que esta página faz</b></legend>
        <p>
            Desenha o formulário. <b>Ela não grava nada.</b>
        </p>
        <p>
            <?php if ($editando) { ?>
                Veio <code>?id=<?php echo $id; ?></code> na URL, então o PHP foi ao banco buscar
                esse paciente e já deixou os campos preenchidos.
                Ao salvar, vai virar um <b>UPDATE</b>.
            <?php } else { ?>
                Não veio id na URL, então os campos estão vazios.
                Ao salvar, vai virar um <b>INSERT</b>.
            <?php } ?>
        </p>
    </fieldset>

    <?php
    // Se o paciente_salvar2.php recusou os dados, ele manda a pessoa de
    // volta para cá com ?erro=algumacoisa na URL. A URL traz só a
    // palavra-código; a frase que aparece na tela está escrita aqui.
    $frases = array(
        'campos'         => 'Faltou preencher nome, nascimento ou sexo.',
        'nao_encontrado' => 'Esse paciente não existe no banco.'
    );

    if (isset($_GET['erro']) && isset($frases[$_GET['erro']])) {
        echo '<p><b>Deu erro:</b> ' . $frases[$_GET['erro']] . '</p>';
    }
    ?>

    <!-- ==================================================================
     O FORMULÁRIO

     action = para QUAL arquivo os dados vão quando apertar o botão.
     method = COMO eles vão.

       post -> viajam escondidos, não aparecem na barra de endereço.
               É o certo para quem vai gravar no banco.
       get  -> viajariam na URL, à vista de todos. É o que esta
               própria página usa para receber o ?id.
     ================================================================== -->

    <form action="paciente_salvar2.php" method="post">
        <?php if ($editando) { ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php } ?>

        <p>
            <label for="nome">Nome Completo</label><br>
            <input type="text" id="nome" name="nome" size="50"
                value="<?php echo htmlspecialchars($nome); ?>">

        </p>

        <p>
            <label for="nascimento">Data de Nascimento</label><br>
            <input type="date" id="nascimento" name="nascimento"
                value="<?php echo htmlspecialchars($nascimento); ?>">
        </p>
        <p>
            <label for="sexo">Sexo</label><br>
            <select id="sexo" name="sexo">
                <option value="">>-- Escolha --< </option>
                <option value="F"><?php if ($sexo == 'F') {
                                        echo 'selected';
                                    } ?>>Feminino</option>
                <option value="M"><?php if ($sexo == 'M') {
                                        echo 'selected';
                                    } ?>>Masculino</option>
                <option value="O"><?php if ($sexo == 'O') {
                                        echo 'selected';
                                    } ?>>Outro</option>
            </select>
        </p>
        <p>
            <label for="telefone">Telefone</label><br>
            <input type="tel" id="telefone" name="telefone" size="20"
                value="<?php echo htmlspecialchars($telefone); ?>">
        </p>
        <p>
            <button type="submit">
                <?php echo ($editando ? 'Salvar alterações' : "Cadastrar") ?>;
            </button>
        </p>
    </form>

</body>

</html>