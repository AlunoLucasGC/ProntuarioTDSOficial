<?php
// ===================================================================
//  includes/funcoes2.php   —   versão de estudo, sem CSS
//
//  AS FUNCTIONS DAS TELAS 2
//
//  Este arquivo NÃO TEM TELA. Se você abrir ele no navegador, a
//  página sai em branco — e está certo. Ele só guarda functions.
//  Quem tem tela é quem chama.
//
//
//  O QUE É UMA FUNCTION
//
//  É um pedaço de código com nome. Escrito UMA vez aqui, e qualquer
//  página chama pelo nome, quantas vezes quiser:
//
//      function nomeDoSexo($letra)      <- a receita, mora aqui
//      echo nomeDoSexo('F');            <- o pedido, lá na página
//
//  Entra alguma coisa pelo parêntese. Sai alguma coisa pelo return.
//  A página que chama não precisa saber como a function faz.
//
//
//  POR QUE NÃO ESCREVER DIRETO NA PÁGINA
//
//  Porque a mesma pergunta se repete em várias telas. "Como a data
//  aparece na tela?" vale para a lista, para o formulário e para o
//  histórico. Se a resposta estiver copiada nos três lugares, basta
//  alguém corrigir num deles para o sistema falar duas línguas.
//
//      UMA VERDADE, UM LUGAR.
//
//  É o que o includes/perfis.php e o includes/faixas.php fazem no
//  sistema de verdade. Este arquivo é a versão pequena deles, e cada
//  function abaixo diz qual function real ela está imitando.
//
//
//  COMO USAR NUMA PÁGINA — uma linha, lá em cima:
//
//      require 'includes/funcoes2.php';
//
//  Só inclui quem usa. O paciente_inativar2.php, por exemplo, não
//  chama nenhuma destas — então não inclui este arquivo.
// ===================================================================


// -------------------------------------------------------------------
//  1. listaDeSexos()   —   A LISTA ÚNICA
//
//  Devolve a tabela de tradução: de um lado a letra que o banco
//  guarda, do outro a palavra que aparece na tela.
//
//  Repare que ela não recebe nada pelo parêntese. Só devolve.
//
//  Por que as letras são F, M e O: a coluna sexo no banco é
//  ENUM('F','M','O') e não aceita outra coisa. A lista aqui tem que
//  bater com a lista de lá.
//
//  No sistema de verdade: listaDePerfis() do includes/perfis.php e
//  listaDeSinais() do includes/faixas.php são esta mesma function,
//  com outro assunto.
// -------------------------------------------------------------------
function listaDeSexos(){
    return array(
        'F' => 'Feminino',
        'M' => 'Masculino',
        'O' =>  'Outro'
    );
}

// -------------------------------------------------------------------
//  2. nomeDoSexo()   —   O TRADUTOR
//
//  Entra a letra, sai a palavra:   'F'  ->  'Feminino'
//
//  Os três passos:
//    a) pega a lista chamando a function de cima — uma function pode
//       chamar outra, e é assim que a lista fica num lugar só;
//    b) isset() pergunta se essa letra existe na lista;
//    c) existe? devolve a palavra. Não existe? devolve a letra como
//       veio.
//
//  O passo (c) é o mais importante e o menos óbvio. Se alguém mexer
//  no banco pelo Workbench e gravar um 'X', esta function devolve
//  'X' — feio, mas a tela abre. Sem ele a página quebraria, e uma
//  tela de prontuário não pode quebrar por causa de um rótulo.
//
//  No sistema de verdade: nomeDoPerfil(), nomeDoTipo(), nomeDaVia()
//  e nomeDoStatus() são todas exatamente este mesmo desenho.
// -------------------------------------------------------------------
function nomeDoSexo($letra){
    $sexos = listaDeSexos();

    

}