<?php
/**
 * Funções utilitárias para plugin da Escola Modelo
 */

require_once($CFG->dirroot.'/config.php');
include_once($CFG->dirroot . '/blocks/escola_modelo/lib/httpful.phar');
include_once($CFG->dirroot . '/course/externallib.php');

define("CURSO_CUSTOMFIELD_PUBLICO",    "publico");
define("CURSO_CUSTOMFIELD_AREATEMATICA",    "areatematica");
define("CURSO_CUSTOMFIELD_CARGAHORARIA",    "cargahoraria");
define("CURSO_CUSTOMFIELD_SENADOR",    "senador");
define("CURSO_CUSTOMFIELD_MUNICIPIO",    "municipio");
define("CURSO_CUSTOMFIELD_TIPOOFICINA",    "tipooficina");
define("CURSO_CUSTOMFIELD_INSTRUTOR",    "instrutor");
define("CURSO_CUSTOMFIELD_MONITOR",    "monitor");

/**
 * Verifica se um curso é público, conforme critérios da EVL.
 * Pelas regras estabelecidas, um curso é público se foi marcado como público 
 * em campo customizado
 */
function cursoPublico($course) {
    global $DB;

    // Um curso é público se estiver marcado como público em campo personalizado
    $publico = (obtemCampoCustomizadoCurso($course->id, CURSO_CUSTOMFIELD_PUBLICO) == '1');
    return $publico;
}

function evlHabilitada() {
    $config = get_config('block_escola_modelo');
    return ($config->config_habilitar_evl == 1);
}

// TODO mover para outro local, usado também em certificado
function obtemCampoCustomizadoCurso($idCurso, $nomeCampo) {
    global $DB;

    $sql = "
        SELECT d.value, f.configdata::json->>'options' as options
        FROM mdl_course c
        JOIN mdl_context ctx
            ON c.id = ?
                AND ctx.contextlevel = 50
                AND ctx.instanceid = c.id
        JOIN mdl_customfield_field f
            ON f.shortname = ?
        JOIN mdl_customfield_data d
            ON d.fieldid = f.id
                AND d.contextid = ctx.id
        ";
    
    $valueArray = $DB->get_record_sql($sql, [$idCurso, $nomeCampo]);
    $value = $valueArray->value;
    $options = $valueArray->options;

    if($options == null) {
        return $value;
    } else {
        
        $optionsArray = preg_split("/\s*\n\s*/", trim($options));
        return $optionsArray[$value-1];
    }
}

/**
 * Registra um determinado curso na EVL, com o status informado
 * 
 * Formato do JSON: 
 * {
 *   "school": "Iniciais da escola",
 *   "course":
 *   {
 *      "name": "nome do curso",
 *      "url": "url do curso",
 *      "description": "descrição do curso",
 *      "logo":"endereco da logo",
 *      "ead_id": "id do curso no moodle",
 *      "visible": Visibilidade do curso(Público ou privado, de acordo com a categoria do moodle),
 *      "conteudista": "",
 *      "certificador": "",
 *      "carga_horaria": ""
 *   },
 *   "key": "k4B5YcbKa619ohu3wxk2xXbmtoxFuQqrwcKEOTAnZi7iy4tl9z"
 * }
 */
function atualizaCursoEVL($curso, $visivel = null) {
    global $DB, $CFG, $USER;

    if( evlHabilitada() ) {
        // Detecta status, caso ele não tenha sido especificado
        $visivel = $visivel ?? cursoPublico($curso);
        
        // Hack: enquanto não há campos personalizados no curso, a carga horária
        // precisa ser obtida a partir do idnumber
        $idnumber = $curso->idnumber;
        $ch = 0;
        if(preg_match("/\_CH([0-9]+)/", $idnumber, $x)) {
            $ch = $x[1];
        }

        $school = $DB->get_record('course',array('id'=>'1'));        
        
        $uri = $CFG->emURLWS . '/api/v1/cursos/registrar/';

        $obj = new StdClass();

        $camposCurso = array( 
            "name" => $curso->fullname,
            "url" => "",
            "description" => $curso->summary,
            "logo" => "",
            "ead_id" => $curso->id,
            "visible" => $visivel,
            "conteudista" => "", 
            "certificador" => $CFG->emSigla,
            "carga_horaria" => $ch
        );

        // Monta o JSON que será enviado ao Web Service
        $obj->school = $CFG->emSigla; 
        $obj->course = $camposCurso;
        $obj->key = $CFG->emApplicationToken;
        $json = json_encode($obj);

        $response = \Httpful\Request::post($uri)
            ->sendsJson()
            ->body($json)
            ->send();
        
        // Se o registro foi criado no servidor, registra em tabela de controle
        if(!$response->hasErrors()) {
            registraSincronizacaoCurso($curso);
        } else {
            mtrace("Erro sincronizando ". $curso->fullname . ": " . $response->code . " " );
        }
    }
}

/**
 * Insere ou atualiza registro da última sincronização de determinado curso
 */
function registraSincronizacaoCurso($curso) {
    global $DB;
    
    // VALUES (' . $curso->id . ',' . date('H:i:s') . ')
        // ON CONFLICT (' . $curso->id . ') DO UPDATE 
        //    SET time_sync = ?';
    $qry = '
        INSERT INTO {ilb_sync_course} (course_id, time_sync) 
        VALUES (?,?)
        ON CONFLICT (course_id) DO UPDATE 
           SET time_sync = ?';
    $params = array($curso->id, $curso->timemodified, $curso->timemodified);
    
    return $DB->execute($qry, $params);
}

/**
 * Identifica e atualiza registro de todos os cursos da categoria especificada
 * (excluindo subcategorias)
 */
function atualizaCategoriaEVL($categoria) {
    global $DB;

    $visivel = categoriaPublica($categoria);
    
    $cursos = $DB->get_records('course', array('category'=>$categoria->id)); 
    
    foreach ($cursos as $curso) {
        atualizaCursoEVL($curso, $visivel);
    }
}

//
// Matrículas
//

// Retirado pois supõe-se que matrículas serão feitas apenas na EVL
// function atualizaMatriculaEVL($matricula) {
//     global $DB;

//     // Detecta status, caso ele não tenha sido especificado
//     $visivel = true; //$visivel ?? cursoPublico($curso);
    
//     // Hack: enquanto não há campos personalizados no curso, a carga horária
//     // precisa ser obtida a partir do idnumber
//     $idnumber = $curso->idnumber;
//     $ch = 0;
//     if(preg_match("/\_CH([0-9]+)/", $idnumber, $x)) {
//         $ch = $x[1];
//     }

//     $school = $DB->get_record('course',array('id'=>'1'));        
    
//     $uri = $CFG->emURLWS . '/api/v1/cursos/registrar/';

//     $obj = new StdClass();

//     $camposCurso = array( 
//         "name" => $curso->fullname,
//         "url" => "",
//         "description" => $curso->summary,
//         "logo" => "",
//         "ead_id" => $curso->id,
//         "visible" => $visivel,
//         "conteudista" => "", //$school->shortname,
//         "certificador" => $school->shortname,
//         "carga_horaria" => $ch
//     );

//     // Monta o JSON que será enviado ao Web Service
//     $obj->school = $school->shortname; // sigla da escola
//     $obj->course = $camposCurso;
//     $obj->key = "k4B5YcbKa619ohu3wxk2xXbmtoxFuQqrwcKEOTAnZi7iy4tl9z";

//     $json = json_encode($obj);

//     $response = \Httpful\Request::post($uri)
//         ->sendsJson()
//         ->body($json)
//         ->send();
    
//     // Se o registro foi criado no servidor, registra em tabela de controle
//     if(!$response->hasErrors()) {
//         registraSincronizacaoMatriculaUsuario($matricula);
//     } else {
//         mtrace("Erro sincronizando ". $matricula->fullname . ": " . $response->code . " " );
//     }
// }

// /**
//  * Insere ou atualiza registro da última sincronização de determinada matricula
//  */
// function registraSincronizacaoMatriculaUsuario($userEnrolment) {
//     global $DB;

//     $qry = '
//         INSERT INTO {ilb_sync_user_enrolments} (user_enrolment_id, time_sync) 
//         VALUES (?,?)
//         ON CONFLICT (user_enrolment_id) DO UPDATE 
//             SET time_sync = ?';
//     $params = array($userEnrolment->id, $userEnrolment->timemodified, $userEnrolment->timemodified);

//     return $DB->execute($qry, $params);
// }

//
// CERTIFICADOS
// 

function atualizaCertificadoEVL($certificado) {
    global $DB, $CFG, $USER;
    mtrace("certificado " . $certificado->code);

    $school = $DB->get_record('course',array('id'=>'1'));        
    
    $uri = $CFG->emURLWS . '/api/v1/certificados/adicionar/';

    $obj = new StdClass();
    $certArray = array();

    // Gravação de certificado para envio ao Web Service da EVL
    $certItem = array(
        'course' => $certificado->course,
        'student' => $certificado->user,
        'date' => $certificado->timecreated,
        'grade' => $certificado->gradefmt,
        'code' => $certificado->id,
    );
    array_push($certArray, $certItem);
    $mainArray = array(
        'key' => $CFG->emApplicationToken,
        'school' => $CFG->emSigla, 
        'certificates' => $certArray,
    );
    
    $json = json_encode($mainArray);
    echo "AQUI O CERT ARRAY -> {$json}\n";
    
    $response = \Httpful\Request::post($uri)
        ->sendsJson()
        ->body($json)
        ->send();    

    // Se o registro foi criado no servidor, registra em tabela de controle
    if(!$response->hasErrors()) {
        registraSincronizacaoCertificado($certificado);
    } else {
        mtrace("Erro sincronizando certificado " . $certificado->code . ": " . $response->code . " " );
    }
}

/**
 * Insere ou atualiza registro da última sincronização de determinado certificado
 */
function registraSincronizacaoCertificado($certificado) {
    global $DB;
    $qry = '
        INSERT INTO {ilb_sync_certificate} (certificate_id, time_sync) 
        VALUES (?,?)
        ON CONFLICT (certificate_id) DO UPDATE 
            SET time_sync = ?';
    $params = array($certificado->id, $certificado->timecreated, $certificado->timecreated);

    return $DB->execute($qry, $params);
}

//
// DADOS DA ESCOLA
// 

function atualizaDadosEscola($dadosEscola) {
    global $DB, $CFG, $USER;

    $school = $DB->get_record('course',array('id'=>'1'));        
    
    $uri = $CFG->emURLWS . '/api/v1/escolas/registrar/';

    $obj = new StdClass();

    // Gravação de certificado para envio ao Web Service da EVL
    $schoolArray = array(
        'name' => $dadosEscola->nome_escola,
        'url' => $dadosEscola->url_escola,
        'logo' => $dadosEscola->url_logo_escola,
        'initials' => $dadosEscola->sigla_escola,
        'key' => $CFG->emApplicationToken 
    );
    
    $json = json_encode($schoolArray);
    
    $response = \Httpful\Request::post($uri)
        ->sendsJson()
        ->body($json)
        ->send();
    
    // Se o registro foi criado no servidor, registra em tabela de controle
    if($response->hasErrors()) {
        mtrace("Erro sincronizando dados da escola " . $dadosEscola->sigla_escola);
    }
}


