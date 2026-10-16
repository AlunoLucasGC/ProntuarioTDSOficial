<?php
// ===================================================================
//  paciente_form2.php   —   versão de estudo, sem CSS
//
//  O "C" e o "U" do CRUD, na mesma tela:
//      sem ?id  ->  formulário VAZIO   -> vai virar INSERT
//      com ?id  ->  formulário CHEIO   -> vai virar UPDATE
//
//  É o mesmo arquivo para as duas coisas. O que muda é UMA pergunta:
//  veio id na URL ou não veio?
//
//  ATENÇÃO: esta tela não grava nada. Ela só desenha o formulário.
//  Quem grava é o paciente_salvar2.php, para onde o botão aponta.
// ===================================================================


require 'config/conexao.php';


// PASSO 1 — COMEÇAR COM TUDO EM BRANCO.
// Se for um cadastro novo, é isto que vai aparecer nos campos.
$id         = 0;
$nome       = '';
$nascimento = '';
$sexo       = '';
$telefone   = '';
$editando   = false;


// PASSO 2 — VEIO ID NA URL?
// paciente_form2.php?id=7  ->  $_GET['id'] vale 7.
// paciente_form2.php       ->  $_GET['id'] não existe.
if (isset($_GET['id'])) {

    // (int) força o valor a virar número inteiro. Se alguém digitar
    // ?id=abc na barra de endereço, isso vira 0 e não quebra nada.
    $id = (int) $_GET['id'];

    // PASSO 3 — BUSCAR ESSE PACIENTE NO BANCO.
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

    $stmt = mysqli_prepare($conexao, $sql);   // prepara a consulta
    mysqli_stmt_bind_param($stmt, 'i', $id);  // 'i' = o buraco é um inteiro
    mysqli_stmt_execute($stmt);               // agora sim vai ao banco

    $resultado = mysqli_stmt_get_result($stmt);
    $paciente  = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    // Pediram um id que não existe? Volta para a lista com o recado.
    if (!$paciente) {
        header('Location: paciente_listar2.php?erro=nao_encontrado');
        exit;
    }

    // PASSO 4 — JOGAR O QUE VEIO DO BANCO NAS VARIÁVEIS.
    // São as mesmas variáveis do passo 1. A diferença é que agora
    // elas têm conteúdo — e é esse conteúdo que aparece nos campos.
    $nome       = $paciente['nome'];
    $nascimento = $paciente['data_nascimento'];
    $sexo       = $paciente['sexo'];
    $telefone   = $paciente['telefone'];
    $editando   = true;
}

mysqli_close($conexao);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?php echo ($editando ? 'Editar paciente' : 'Cadastrar paciente'); ?></title>
  <link rel="stylesheet" href="assets/vendor/css/core.css">
</head>
<body class="bg-body-tertiary">

<div class="container py-4">


<h1 class="h3 mb-1">
  <?php
  // O título da tela muda junto. Quem está usando precisa saber se
  // está criando gente nova ou corrigindo alguém que já existe.
  echo ($editando ? 'Editar paciente — o U do CRUD' : 'Cadastrar paciente — o C do CRUD');
  ?>
</h1>

<div class="card mb-4">
  <div class="card-body">
  <h2 class="h6 text-primary mb-2"> que esta página faz</h2>
  <p class="mb-2">
    Desenha o formulário. <strong>Ela não grava nada.</strong>
  </p>
  <p class="mb-0 small text-primary">
    <?php if ($editando) { ?>
      Veio <code>?id=<?php echo $id; ?></code> na URL, então o PHP foi ao banco buscar
      esse paciente e já deixou os campos preenchidos.
      Ao salvar, vai virar um <b>UPDATE</b>.
    <?php } else { ?>
      Não veio id na URL, então os campos estão vazios.
      Ao salvar, vai virar um <strong>INSERT</strong>.
    <?php } ?>
  </p>
  </div>
</div>

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
  <!-- ================================================================
       O CAMPO ESCONDIDO

       Este campo não aparece na tela, mas viaja junto com o resto.
       É ele que avisa o paciente_salvar2.php: "não é gente nova,
       é o paciente de número tal, então faça UPDATE".

       Sem ele, salvar uma edição criaria um paciente repetido.
       ================================================================ -->
  <input type="hidden" name="id" value="<?php echo $id; ?>">
<?php } ?>

  <div class="card">
    <div class="card-body">
      <div class="mb-3">
    <label class="form-label" for="nome">Nome completo</label>
    <?php
    // O value é o que já vem escrito no campo. No cadastro novo ele
    // está vazio; na edição, traz o que estava gravado.
    ?>
    <input type="text" class="form-control" id="nome" name="nome" 
           value="<?php echo htmlspecialchars($nome); ?>">


</div>
  <div class="row">
    <div class="col-md-4 mb-3">
    <label class="form-label" for="nascimento">Data de nascimento</label>
    <?php
    // type="date" abre o calendário do navegador e já entrega a data
    // no formato aaaa-mm-dd, que é exatamente o que o MySQL espera.
    ?>
    <input class="form-control" type="date" id="nascimento" name="nascimento"
           value="<?php echo htmlspecialchars($nascimento); ?>">
    </div>



  <div class="col-md-4 mb-3">
    <label class="form-label" for="sexo">Sexo</label>
    <?php
    // A coluna do banco é ENUM('F','M','O'): só aceita essas três
    // letras. Por isso a lista tem exatamente essas três opções.
    // O selected marca a que já está gravada, na hora de editar.
    ?>
    <select class="form-select" id="sexo" name="sexo">
      <option value="">-- escolha --</option>
      <option value="F" <?php if ($sexo == 'F') { echo 'selected'; } ?>>Feminino</option>
      <option value="M" <?php if ($sexo == 'M') { echo 'selected'; } ?>>Masculino</option>
      <option value="O" <?php if ($sexo == 'O') { echo 'selected'; } ?>>Outro</option>
    </select>
</div>

  <div class="col-md-4 mb-3">
    <label class="form-label" for="telefone">Telefone</label>
    <input class="form-control" type="text" id="telefone" name="telefone" size="20"
           value="<?php echo htmlspecialchars($telefone); ?>">
</div>
</div>

  <div>
    <?php
    // O texto do botão também muda. É o mesmo botão e o mesmo destino;
    // só a palavra é diferente, para a pessoa saber o que vai acontecer.
    ?>
    <button type="submit" class="btn btn-primary">
      <?php echo ($editando ? 'Salvar alterações (UPDATE)' : 'Cadastrar (INSERT)'); ?>
    </button>
    <a href="paciente_listar2.php" class="btn btn-outline-primary">Cancelar e voltar para a lista</a>
</div>
    </div>
    </div>

</form>

<hr>

<fieldset>
  <legend><b>Para onde os dados vão agora</b></legend>
  <p>
    <!-- ATENÇÃO, é a única linha da página com código no lugar de letra.
         Na linha de baixo, para aparecer na tela a palavra form entre o
         sinal de menor e o de maior, é preciso escrever esses dois sinais
         em código. Escritos direto, o navegador entenderia que começou uma
         etiqueta e abriria um formulário de verdade no meio do texto.
         Acento é outra história: á, ã e ç vão escritos como são, porque o
         meta charset utf-8 lá em cima já avisou o navegador em que língua
         ler este arquivo. -->
    Ao apertar o botão, o navegador empacota tudo que está dentro do
    <code>&lt;form&gt;</code> e entrega para o <code>paciente_salvar2.php</code>.<br>
    Lá, cada campo chega dentro de <code>$_POST</code>, com o mesmo
    <code>name</code> que está escrito aqui:
    <code>$_POST['nome']</code>, <code>$_POST['nascimento']</code>,
    <code>$_POST['sexo']</code>, <code>$_POST['telefone']</code>.
  </p>
  <p>
    <b>Campo sem <code>name</code> não viaja.</b> Fica bonito na tela e some no caminho.
  </p>
</fieldset>
</div>

</body>
</html>