```php
<?php
// ===================================================================
//  paciente_salvar.php  —  Grava o cadastro do paciente
//  Módulo 2 · Sprint 3 · perfil recepção
//
//  Sem id  -> INSERT (pessoa nova)
//  Com id  -> UPDATE (correção de cadastro)
//
//  NÃO interna ninguém. Este arquivo mexe só na tabela `pacientes`,
//  que guarda quem a pessoa é. Colocar num leito é gravar em
//  `internacoes`, e isso acontece no internacao_admitir.php.
//
//  A permissão se repete aqui, no arquivo que GRAVA.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('recepcao'));

require 'config/conexao.php';
require 'includes/alergia.php';



// A alergia vem em DUAS partes: a resposta e, quando for o caso, o
// texto. A coluna do banco continua sendo uma só — ela é montada mais
// abaixo, depois de as duas partes serem conferidas.

// ===================================================================
//  VALIDAÇÃO
//  O required do HTML ajuda quem usa a tela, mas não protege o
//  sistema: o formulário pode chegar sem passar por ela.
// ===================================================================


// -------------------------------------------------------------------
//  A ALERGIA — três recusas, e só então o texto é montado
//
//  Antes, o campo era texto livre obrigatório, e a tela pedia que a
//  pessoa escrevesse a frase "Nega alergias" quando não houvesse
//  alergia. Quem escrevia "Nenhuma" não desobedecia: respondia a
//  pergunta que foi feita. E aparecia com tarja vermelha de alergia
//  em dezessete telas.
//
//  Agora não existe frase para interpretar na entrada.
// -------------------------------------------------------------------

// 1. Nenhuma das duas respostas. É a recusa mais importante: campo em
//    branco não distingue "não tem" de "ninguém perguntou", e essa
//    diferença é clínica.

$resposta_alergia = isset($_POST['alergia_resposta'])
    ? trim($_POST['alergia_resposta'])
    : '';

$texto_alergia = isset($_POST['alergias'])
    ? trim($_POST['alergias'])
    : '';

// 2. Disse que tem, e não disse qual.

if ($resposta_alergia === 'sim' && $texto_alergia === '') {
    die('Informe qual alergia o paciente possui.');
}

// 3. Disse que tem, e escreveu uma negação. A mesma função que decide
//    o alerta nas telas serve para pegar a contradição aqui — se ela
//    reconhece o texto como negação, as duas respostas não combinam.

if (
    $resposta_alergia === 'sim' &&
    function_exists('alergiaEhNegacao') &&
    alergiaEhNegacao($texto_alergia)
) {
    die('A resposta informa que possui alergia, mas o texto informa uma negação');
}

// Agora sim: uma coluna, montada a partir de uma resposta sem ambiguidade.

if ($resposta_alergia === 'sim') {
    $alergias = $texto_alergia;
} else {
    $alergias = 'Nega alergias';
}


// A data precisa ser uma data de verdade. checkdate() recusa
// 31 de fevereiro, por exemplo.

$nascimento = isset($_POST['nascimento'])
    ? trim($_POST['nascimento'])
    : '';

$data = explode('-', $nascimento);

if (
    count($data) !== 3 ||
    !checkdate(
        (int) $data[1],
        (int) $data[2],
        (int) $data[0]
    )
) {
    die('A data de nascimento é inválida');
}


// REGRA DE OURO Nº 7 — data futura não entra.

if ($nascimento > date('Y-m-d')) {
    die('A data de nascimento não pode ser futura');
}


// ===================================================================
//  GRAVAÇÃO
// ===================================================================

$nome = isset($_POST['nome'])
    ? trim($_POST['nome'])
    : '';

$sexo = isset($_POST['sexo'])
    ? trim($_POST['sexo'])
    : '';

$cartao = isset($_POST['cartao'])
    ? trim($_POST['cartao'])
    : '';

$telefone = isset($_POST['telefone'])
    ? trim($_POST['telefone'])
    : '';

$endereco = isset($_POST['endereco'])
    ? trim($_POST['endereco'])
    : '';

$responsavel = isset($_POST['responsavel'])
    ? trim($_POST['responsavel'])
    : '';

if ($nome === '') {
    die('O nome do paciente é obrigatório');
}

if ($sexo === '') {
    die('O sexo do paciente é obrigatório');
}

$id = isset($_POST['id'])
    ? (int) $_POST['id']
    : 0;


// ------------------------------------------------------------
//  CADASTRO NOVO
//
//  `cadastrado_por` sai da sessão, nunca do formulário. Regra
//  de Ouro nº 2: todo registro tem autor, e o autor não se
//  digita.
//
//  Repare que o paciente nasce SEM internação. Ele existe no
//  sistema, mas não está em leito nenhum — e é assim que deve
//  ser: cadastrar não é internar.
// ------------------------------------------------------------

if ($id === 0) {

    $quem_cadastrou = isset($_SESSION['usuario_id'])
        ? (int) $_SESSION['usuario_id']
        : 0;

    if ($quem_cadastrou === 0) {
        die('Usuário não identificado');
    }

    $sql = "INSERT INTO pacientes (
            nome,
            nascimento,
            sexo,
            cartao,
            telefone,
            endereco,
            responsavel,
            alergias,
            cadastrado_por
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexao, $sql);

    if (!$stmt) {
        die('Erro ao preparar o cadastro: ' .
            mysqli_error($conexao));
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssi',
        $nome,
        $nascimento,
        $sexo,
        $cartao,
        $telefone,
        $endereco,
        $responsavel,
        $alergias,
        $quem_cadastrou
    );

    if (!mysqli_stmt_execute($stmt)) {
        die('Erro ao cadastrar paciente: ' .
            mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    $aviso = 'cadastrado';
} else {

    $sql = "UPDATE pacientes
                SET
                    nome = ?,
                    nascimento = ?,
                    sexo = ?,
                    cartao = ?,
                    telefone = ?,
                    endereco = ?,
                    responsavel = ?,
                    alergias = ?
                WHERE paciente_id = ?";

    $stmt = mysqli_prepare($conexao, $sql);

    if (!$stmt) {
        die('Erro ao preparar a atualização: ' .
            mysqli_error($conexao));
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssi',
        $nome,
        $nascimento,
        $sexo,
        $cartao,
        $telefone,
        $endereco,
        $responsavel,
        $alergias,
        $id
    );

    if (!mysqli_stmt_execute($stmt)) {
        die('Erro ao atualizar paciente: ' .
            mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    $aviso = 'atualizado';
}


mysqli_close($conexao);

header('Location: paciente_listar.php?ok=' . $aviso);
exit;
