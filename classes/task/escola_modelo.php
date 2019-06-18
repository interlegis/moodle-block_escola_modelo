<?php

/**
 * escola_modelo.php
 * 
 * Implementa procedimentos para sincronização de informações de uma instalação
 * da Escola Modelo com o servidor da EVL 
 *
 **/

namespace block_escola_modelo\task;


global $CFG;

require_once($CFG->dirroot.'/config.php');
require_once($CFG->dirroot.'/blocks/escola_modelo/classes/util.php');

class escola_modelo extends \core\task\scheduled_task {      
	
	public function get_name() {
        return get_string('escola_modelo', 'block_escola_modelo');
    }
                                                                     
	// Ponto de entrada da task
	public function execute() {       
		global $DB;
		// Momento do início do procedimento, para fins de gravação em 
		// tabelas de controle
		$syncStartTime = $DB->get_record_sql('SELECT extract(epoch from now())::int8');

		$this->sincronizaDadosEscola($syncStartTime);
		//$this->sincronizaCursos($syncStartTime);
		//$this->sincronizaMatriculas($syncStartTime);
		//$this->sincronizaCertificados($syncStartTime);
	}

	/**
	 * Realiza procedimento de sincronização nos cursos criados e/ou atualizados
	 * que ainda não foram atualizados na EVL
	 */
	public function sincronizaDadosEscola($syncStartTime) {
		global $DB, $CFG;
        mtrace("Iniciando sincronização de dados da escola");

		// Obtem todos os cursos pendentes de sincronização
		$sqlDadosEscola = '
			SELECT ?::varchar as sigla_escola, 
				?::varchar as url_escola, 
				c.fullname as nome_escola,
				(? || \'/pluginfile.php/1/core_admin/logocompact/0x150/-1\' || logo.value)::varchar as url_logo_escola
			FROM {course} c
				JOIN (
					SELECT value
					FROM {config_plugins} 
					WHERE plugin = \'core_admin\' 
						AND name = \'logocompact\'
				) logo
				ON 1 = 1
			WHERE c.id = 1
		';

		$dadosEscola = $DB->get_record_sql($sqlDadosEscola,array(evlSiglaEscola(), $CFG->wwwroot, $CFG->wwwroot));

		// Atualiza cada um dos cursos pendentes
		atualizaDadosEscola($dadosEscola);

		mtrace("Fim da sincronização de dados da escola");
	}


	/**
	 * Realiza procedimento de sincronização nos cursos criados e/ou atualizados
	 * que ainda não foram atualizados na EVL
	 */
	public function sincronizaCursos($syncStartTime) {
		global $DB;
        mtrace("Iniciando sincronização de cursos");

		// Obtem todos os cursos pendentes de sincronização
		$sqlCourses = '
			SELECT c.*
			FROM {course} c
				LEFT JOIN {ilb_sync_course} sc
					ON c.id = sc.course_id
			WHERE (sc.course_id is null
				OR c.timemodified > sc.time_sync)
		';

		$listaCursos = $DB->get_records_sql($sqlCourses,array());

		// Atualiza cada um dos cursos pendentes
		foreach($listaCursos as $curso) {
			atualizaCursoEVL($curso);
		}
		mtrace("Fim da sincronização de cursos");
	}

	/**
	 * Realiza procedimento de sincronização de matrículas de usuários a cursos,
	 * as quais ainda não foram atualizadas na EVL
	 */
	public function sincronizaMatriculas($syncStartTime) {
		atualizaMatriculas($syncStartTime);
	}
	
	/**
	 * Realiza procedimento de sincronização de certificados ainda não
	 * atualizados na EVL
	 */
	public function sincronizaCertificados($syncStartTime) {
		global $DB;
		mtrace("Iniciando sincronização de certificados");

		// Identifica eventuais cursos não sincronizados
		$sqlCertificates = '
			SELECT c.id as courseid, u.username, ci.timecreated, gg.finalgrade, ci.code, cert.id
			FROM (
				SELECT ci.timecreated, sc.time_sync, ci.code, ci.certificateid, ci.userid
				FROM {certificate_issues} ci
					LEFT JOIN {ilb_sync_certificate} sc
						ON ci.id = sc.certificate_id
				WHERE sc.time_sync is null
					OR ci.timecreated > sc.time_sync
			) ci
				JOIN mdl_certificate cert
					ON cert.id = ci.certificateid
				JOIN mdl_course c
					ON c.id = cert.course
				JOIN mdl_user u
					ON u.id = ci.userid
				LEFT JOIN mdl_grade_items gi
					ON gi.courseid = c.id
						AND gi.itemtype =  \'course\'
				LEFT JOIN mdl_grade_grades gg
					ON gg.itemid = gi.id
						AND gg.userid = ci.userid
		';

		$certificados = $DB->get_records_sql($sqlCertificates,array());

		// Processa cada certificado, gerando chamada ao web service 
		foreach ($certificados as $certificado) {
			atualizaCertificadoEVL($certificado); 
		}
		mtrace("Fim da sincronização de certificados");
	}                                                                                                                               
} 
?>
