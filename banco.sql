-- ============================================================
-- PRONTUÁRIO ELETRÔNICO TDS - Reset entre turmas
-- Modelo com 11 tabelas — o mesmo do banco_prontuario_tds.sql
--
-- Apaga os dados que a turma produziu e recarrega os três
-- pacientes de exemplo, com o plantão de ontem preenchido e o de
-- hoje em branco.
--
-- O QUE SOME              O QUE FICA
--   pacientes               usuarios      (os logins da turma)
--   internacoes             setores       (a estrutura do hospital)
--   sinais_vitais           leitos        (as camas)
--   registros               cids          (catálogo de diagnósticos)
--   prescricoes             medicamentos  (catálogo da farmácia)
--   administracoes
--
-- A divisão tem uma lógica: some o que é MOVIMENTO, fica o que é
-- CADASTRO. O hospital continua existindo entre uma turma e outra.
--
-- Quem roda: perfil ADMINISTRADOR, ao final de cada prática
-- com uma turma de Enfermagem.
--
-- ------------------------------------------------------------
-- O QUE ACONTECE DEPOIS, QUANDO ALGUÉM ABRE A TELA DO TURNO
--
-- Este script deixa 10 doses no banco: as de ontem já checadas
-- (exemplo pronto) e algumas de hoje pendentes.
--
-- Mas a tela `medicacao_turno.php` COMPLETA as doses que faltam
-- de toda prescrição ativa — ela é a única tela do sistema que
-- grava num GET, e existe para que prescrição de paciente
-- internado não pare de aparecer. Ver includes/doses.php.
--
-- Efeito prático: no primeiro acesso depois do reset, a tabela
-- `administracoes` salta de 10 para cerca de 76 linhas, porque
-- as quatro prescrições recarregadas passam a ter os horários
-- dos próximos sete dias.
--
-- ISSO É O ESPERADO, não é erro. E a tela continua enxuta: ela
-- mostra só o que já venceu e o que vence no plantão — algo como
-- 6 atrasadas e 6 no horário. O resto aparece resumido numa
-- linha, "mais N doses depois do plantão".
--
-- Vale explicar esse número à turma: uma prescrição de 6/6h por
-- sete dias É 28 doses. A tabela não está inflada; ela está
-- guardando o que a ordem médica realmente mandou.
-- ------------------------------------------------------------
--
-- Todos os dados são FICTÍCIOS.
-- ============================================================

USE prontuario_tds;

-- ============================================================
-- A CODIFICAÇÃO DA CONEXÃO, DECLARADA AQUI DENTRO
--
-- Sem esta linha, o resultado depende de COMO o arquivo é
-- carregado. O MySQL Workbench manda UTF-8 e dá certo; o
-- console do Windows manda na codificação dele e os acentos
-- chegam trocados — "pó" vira "p├│", "injeção" vira
-- "inje├º├úo". Nada dá erro: só fica errado, em todas as
-- linhas com acento, e ninguém percebe até a tela mostrar.
--
-- Medido: sem esta linha os bytes de "ó" chegam como E2949C
-- (o caractere ├) em vez de C3B3. Com ela, chegam certos por
-- qualquer caminho — Workbench, console ou linha de comando.
-- ============================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. LIMPEZA
--    A ordem importa por causa das chaves estrangeiras:
--    apaga-se do "filho" para o "pai". Tentar apagar `pacientes`
--    antes de `internacoes` dá erro 1451 — o banco não deixa
--    deixar registro órfão.
-- ------------------------------------------------------------
DELETE FROM administracoes;
DELETE FROM prescricoes;
DELETE FROM registros;
DELETE FROM sinais_vitais;
DELETE FROM internacoes;
DELETE FROM pacientes;

-- Zera os contadores para os ids recomeçarem em 1
ALTER TABLE administracoes AUTO_INCREMENT = 1;
ALTER TABLE prescricoes    AUTO_INCREMENT = 1;
ALTER TABLE registros      AUTO_INCREMENT = 1;
ALTER TABLE sinais_vitais  AUTO_INCREMENT = 1;
ALTER TABLE internacoes    AUTO_INCREMENT = 1;
ALTER TABLE pacientes      AUTO_INCREMENT = 1;

-- ------------------------------------------------------------
-- 2. DEVOLVE O HOSPITAL AO ESTADO NORMAL
--    Durante a prática a turma pode ter inativado um leito, um
--    setor ou um usuário. Para a próxima turma tudo volta ligado.
-- ------------------------------------------------------------
UPDATE setores      SET ativo = 1;
UPDATE leitos       SET ativo = 1;
UPDATE usuarios     SET ativo = 1;
UPDATE cids         SET ativo = 1;
UPDATE medicamentos SET ativo = 1;

-- ------------------------------------------------------------
-- 3. RECARGA DOS DADOS DE EXEMPLO
--    Datas relativas ao dia em que o script roda.
--
--    Repare que leito, CID e medicamento são buscados pelo NOME,
--    não pelo id. Assim o script funciona mesmo que os ids sejam
--    diferentes nesta instalação — é mais seguro do que confiar
--    que o leito 101-A seja sempre o id 1.
-- ------------------------------------------------------------
INSERT INTO pacientes
  (nome, data_nascimento, sexo, cartao_sus, telefone, responsavel, alergias, cadastrado_por) VALUES
('Maria Aparecida Fontes', '1952-03-14', 'F', '700000000000001', '(55) 99999-0001', 'Cleusa Fontes (filha)', 'Dipirona',      2),
('João Batista Corrêa',    '1978-11-02', 'M', '700000000000002', '(55) 99999-0002', 'Marta Corrêa (esposa)', 'Nega alergias', 2),
('Helena Sartori',         '1995-06-27', 'F', '700000000000003', '(55) 99999-0003', 'Paulo Sartori (irmão)', 'Penicilina',    2);

INSERT INTO internacoes
  (paciente_id, leito_id, situacao, cid_id, data_admissao, admitido_por) VALUES
(1, (SELECT id FROM leitos WHERE identificacao = '101-A'), 'internado',
    (SELECT id FROM cids WHERE codigo = 'J18.9'),
    CURDATE() - INTERVAL 2 DAY + INTERVAL 8 HOUR + INTERVAL 30 MINUTE, 2),
(2, (SELECT id FROM leitos WHERE identificacao = '102-B'), 'internado',
    (SELECT id FROM cids WHERE codigo = 'E11.9'),
    CURDATE() - INTERVAL 1 DAY + INTERVAL 14 HOUR, 2),
(3, (SELECT id FROM leitos WHERE identificacao = '103-A'), 'internado',
    (SELECT id FROM cids WHERE codigo = 'Z98.8'),
    CURDATE() - INTERVAL 1 DAY + INTERVAL 15 HOUR, 2);

-- Sinais vitais do PLANTÃO DE ONTEM
-- internacao_id vem junto: dado clínico pertence à estadia.
INSERT INTO sinais_vitais
  (paciente_id, internacao_id, usuario_id, data_hora, pa_sistolica, pa_diastolica, frequencia_cardiaca, frequencia_respiratoria, temperatura, saturacao, glicemia, escala_dor, observacao) VALUES
(1, 1, 4, CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR, 134, 84,  96, 24, 38.6, 92, NULL, 3, 'Paciente refere cansaço aos esforços'),
(1, 1, 5, CURDATE() - INTERVAL 1 DAY + INTERVAL 18 HOUR, 128, 78,  88, 20, 37.4, 95, NULL, 1, NULL),
(2, 2, 5, CURDATE() - INTERVAL 1 DAY + INTERVAL 18 HOUR, 148, 92,  80, 18, 36.5, 97,  212, 0, 'Glicemia capilar aferida antes do jantar'),
(3, 3, 4, CURDATE() - INTERVAL 1 DAY + INTERVAL 20 HOUR, 112, 72, 104, 20, 36.9, 98, NULL, 5, 'Pós-operatório imediato');

INSERT INTO registros (paciente_id, internacao_id, usuario_id, tipo, data_hora, texto) VALUES
(1, 1, 4, 'anotacao', CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR + INTERVAL 15 MINUTE, 'Paciente acordada, orientada, em ar ambiente. Refere tosse produtiva. Aceitou dieta parcialmente. Sinais vitais aferidos e registrados.'),
(1, 1, 3, 'evolucao', CURDATE() - INTERVAL 1 DAY + INTERVAL  9 HOUR,                      'Paciente com melhora do padrão respiratório, mantendo saturação acima de 92% em ar ambiente. Ausculta com estertores em base direita. Mantida antibioticoterapia. Reavaliar febre no turno da tarde.'),
(3, 3, 4, 'anotacao', CURDATE() - INTERVAL 1 DAY + INTERVAL 20 HOUR + INTERVAL 30 MINUTE, 'Curativo de ferida operatória em abdome inferior, limpo e seco, sem sinais flogísticos. Paciente refere dor 5/10, comunicado profissional responsável.');

-- internacao_id vem junto: a prescrição pertence à internação, não só ao
-- paciente. As três internações recarregadas acima recebem os ids 1, 2 e 3.
INSERT INTO prescricoes (paciente_id, internacao_id, usuario_id, medicamento_id, dose, via, frequencia, horarios, data_inicio, observacao) VALUES
(1, 1, 3, (SELECT id FROM medicamentos WHERE nome = 'Amoxicilina + Clavulanato'),
       '500 mg', 'VO', '8/8h', '06:00,14:00,22:00', CURDATE() - INTERVAL 2 DAY, 'Administrar após alimentação'),
(1, 1, 3, (SELECT id FROM medicamentos WHERE nome = 'Paracetamol'),
       '750 mg', 'VO', 'se necessário', NULL, CURDATE() - INTERVAL 2 DAY, 'Se temperatura axilar acima de 37,8 °C'),
(2, 2, 3, (SELECT id FROM medicamentos WHERE nome = 'Insulina regular'),
       'conforme glicemia', 'SC', '6/6h', '06:00,12:00,18:00,00:00', CURDATE() - INTERVAL 1 DAY, 'Seguir esquema prescrito'),
(3, 3, 3, (SELECT id FROM medicamentos WHERE nome = 'Dipirona'),
       '1 g', 'IV', '6/6h', '06:00,12:00,18:00,00:00', CURDATE() - INTERVAL 1 DAY, NULL);

-- ONTEM já checado (exemplos prontos) | HOJE pendente (exercício da turma)
INSERT INTO administracoes (prescricao_id, usuario_id, horario_previsto, data_hora_checagem, status, justificativa) VALUES
(1, 4,    CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR, CURDATE() - INTERVAL 1 DAY + INTERVAL  6 HOUR + INTERVAL  5 MINUTE, 'administrado',     NULL),
(1, 5,    CURDATE() - INTERVAL 1 DAY + INTERVAL 14 HOUR, CURDATE() - INTERVAL 1 DAY + INTERVAL 14 HOUR + INTERVAL 15 MINUTE, 'administrado',     NULL),
(1, 5,    CURDATE() - INTERVAL 1 DAY + INTERVAL 22 HOUR, CURDATE() - INTERVAL 1 DAY + INTERVAL 22 HOUR + INTERVAL 40 MINUTE, 'nao_administrado', 'Paciente recusou a medicação; profissional responsável comunicado'),
(1, NULL, CURDATE() + INTERVAL  6 HOUR, NULL, 'pendente', NULL),
(1, NULL, CURDATE() + INTERVAL 14 HOUR, NULL, 'pendente', NULL),
(1, NULL, CURDATE() + INTERVAL 22 HOUR, NULL, 'pendente', NULL),
(3, NULL, CURDATE() + INTERVAL  6 HOUR, NULL, 'pendente', NULL),
(3, NULL, CURDATE() + INTERVAL 12 HOUR, NULL, 'pendente', NULL),
(4, NULL, CURDATE() + INTERVAL  6 HOUR, NULL, 'pendente', NULL),
(4, NULL, CURDATE() + INTERVAL 12 HOUR, NULL, 'pendente', NULL);

-- ============================================================
-- CONFERÊNCIA
-- ============================================================
SELECT 'Reset concluído.' AS status,
       (SELECT COUNT(*) FROM usuarios)     AS usuarios_mantidos,
       (SELECT COUNT(*) FROM setores)      AS setores_mantidos,
       (SELECT COUNT(*) FROM leitos)       AS leitos_mantidos,
       (SELECT COUNT(*) FROM cids)         AS cids_mantidos,
       (SELECT COUNT(*) FROM medicamentos) AS medicamentos_mantidos,
       (SELECT COUNT(*) FROM pacientes)    AS pacientes_recarregados,
       (SELECT COUNT(*) FROM internacoes)  AS internacoes_recarregadas,
       (SELECT COUNT(*) FROM prescricoes)  AS prescricoes_recarregadas,
       (SELECT COUNT(*) FROM administracoes) AS doses_recarregadas;

-- ------------------------------------------------------------
-- CONFERÊNCIA 2 — o hospital voltou ao normal?
--
-- Os cinco números têm que ser ZERO. Qualquer um diferente de
-- zero significa que sobrou algo da turma anterior.
-- ------------------------------------------------------------
SELECT 'Tem que dar tudo zero.' AS conferencia,
       (SELECT COUNT(*) FROM internacoes WHERE situacao <> 'internado') AS internacoes_com_alta,
       (SELECT COUNT(*) FROM prescricoes WHERE ativo = 0)               AS prescricoes_suspensas,
       (SELECT COUNT(*) FROM leitos      WHERE ativo = 0)               AS leitos_inativos,
       (SELECT COUNT(*) FROM setores     WHERE ativo = 0)               AS setores_inativos,
       (SELECT COUNT(*) FROM usuarios    WHERE ativo = 0)               AS usuarios_inativos;

-- ------------------------------------------------------------
-- CONFERÊNCIA 3 — sobrou dado clínico sem internação?
--
-- Também tem que dar tudo zero. Se der diferente, alguma linha
-- ficou apontando para uma internação que não existe mais — e a
-- ficha do paciente não conseguiria mostrá-la.
-- ------------------------------------------------------------
SELECT 'Tem que dar tudo zero.' AS orfaos,
       (SELECT COUNT(*) FROM sinais_vitais x
          LEFT JOIN internacoes i ON i.id = x.internacao_id
         WHERE i.id IS NULL) AS sinais_sem_internacao,
       (SELECT COUNT(*) FROM registros x
          LEFT JOIN internacoes i ON i.id = x.internacao_id
         WHERE i.id IS NULL) AS registros_sem_internacao,
       (SELECT COUNT(*) FROM prescricoes x
          LEFT JOIN internacoes i ON i.id = x.internacao_id
         WHERE i.id IS NULL) AS prescricoes_sem_internacao,
       (SELECT COUNT(*) FROM administracoes a
          LEFT JOIN prescricoes p ON p.id = a.prescricao_id
         WHERE p.id IS NULL) AS doses_sem_prescricao;
