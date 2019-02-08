<?php
/**
 * Funções utilitárias para plugin da Escola Modelo
 */

require_once($CFG->dirroot.'/config.php');
include('httpful.phar');

/**
 * Verifica se um curso é público, conforme critérios da EVL.
 * Pelas regras estabelecidas, um curso é público se a categoria raiz em que ele
 * estiver for uma categoria pública.
 */
function cursoPublico($course) {
    global $DB;

    $category = $DB->get_record('course_categories', array('id'=>$course->category));        
    $path = explode('/',$category->path);        
    $root_category_id = $path[1];        
    $root_category = $DB->get_record('course_categories',array('id'=>$root_category_id));        

    return categoriaPublica($root_category);
}

/**
 * Verifica se uma categoria é pública, conforme critérios da EVL
 * Pelas regras estabelecidas, uma categoria é pública se possuir idnumber iniciado por PUB_
 */
function categoriaPublica($category) {
    $idnumber=$category->idnumber;
    $isPublic=(strcasecmp(substr($idnumber,0,4), 'PUB_') == 0);
    
    return $isPublic;
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

    mtrace("curso " . $curso->id);

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
    $obj->key = $USER->idnumber;
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

/**
 * Insere ou atualiza registro da última sincronização de determinado curso
 */
function registraSincronizacaoCurso($curso) {
    global $DB;

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
        'course' => $certificado->courseid,
        'student' => $certificado->username,
        'date' => $certificado->timecreated,
        'grade' => $certificado->finalgrade,
        'code' => $certificado->code,
    );
    array_push($certArray, $certItem);

    $mainArray = array(
        'school' => $CFG->emSigla, 
        'certificates' => $certArray,
        'key' => $USER->idnumber
    );
    $json = json_encode($mainArray);
    
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
	'key' => $USER->idnumber
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


