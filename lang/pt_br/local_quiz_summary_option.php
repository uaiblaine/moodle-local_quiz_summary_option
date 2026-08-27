<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings.
 *
 * @package    local_quiz_summary_option
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['event_summary_option_updated'] = 'Opcao da pagina de resumo do questionario atualizada';
$string['pluginname'] = 'Opcao de resumo do questionario';
$string['privacy:metadata'] = 'O plugin Opcao de resumo do questionario armazena uma configuracao de exibicao por questionario e nao armazena dados pessoais.';
$string['summaryoption'] = 'Pagina de resumo';
$string['summaryoption_help'] = 'Escolha se os estudantes verao a pagina de resumo da tentativa ao finalizar um questionario.

**Mostrar** mantem o comportamento padrao. O estudante chega a uma pagina que lista todas as questoes, na qual as
questoes sem resposta sao sinalizadas, a tentativa pode ser retomada com *Retornar a tentativa* e o envio precisa ser
confirmado.

**Ocultar** envia a tentativa assim que o estudante seleciona *Finalizar tentativa ...*. Como a pagina de resumo e
ignorada, a confirmacao de envio, o aviso sobre questoes sem resposta e o botao *Retornar a tentativa* nao sao
exibidos, e a tentativa nao pode ser retomada.

Esta configuracao vale apenas para a interface web. Tentativas finalizadas no aplicativo Moodle nao sao afetadas.';
$string['summaryoption_hide'] = 'Ocultar';
$string['summaryoption_show'] = 'Mostrar';
$string['summarypageoption'] = 'Opcao da pagina de resumo';
$string['task_cleanup_orphans'] = 'Remover opcoes de pagina de resumo de questionarios excluidos';
