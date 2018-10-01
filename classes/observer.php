<?php

require_once($CFG->dirroot.'/config.php');

include('httpful.phar');

class block_escola_modelo_observer {
    
    //
    // EVENTOS RELACIONADOS A CURSOS
    //

    /*
    {
        "course": {
            "name": "nome do curso",
            "url": "url do curso",
            "course_load": "carga horário do curso",
            "description": "descrição do curso",
            "logo":"endereco da logo",
            "ead_id": "id do curso no moodle"
        }
    }
    */
    public static function course_created(\core\event\base $event) {
        global $DB, $CFG;
        $curso = $DB->get_record($event->objecttable,array('id'=>$event->objectid));

        $idnumber = $curso->idnumber;
        preg_match("/\_CH([0-9]+)/", $idnumber, $x);
        $ch = $x[1];

        //$escola = $DB->get_record("{course}",array('id'=>$curso->));
        $uri = 'https://escolamodelows.interlegis.leg.br/api/v1/cursos/adicionar/';

        $obj = new StdClass();

        $camposCurso = array(
            "name" => $curso->fullname,
            "url" => "", // fixme não deve haver esse campo
            "course_load" => $ch, // fixme como obter esse campo no Moodle
            "description" => $curso->summary,
            "logo" => "", // fixme não deve ter esse campo
            "ead_id" => $curso->id
        );

        $obj->course = $camposCurso;
        $obj->school = "SSL";
        $obj->category = "1";
        $json = json_encode($obj);

        $response = \Httpful\Request::post($uri)
            ->sendsJson()
            ->body($json)
            ->send();
    }

    /*
    {
        "school": "iniciais",
        "course": {
            "name": "nome do curso",
            "url": "url do curso",
            "course_load": "carga horário do curso",
            "description": "descrição do curso",
            "logo":"endereco da logo, se vazio permanece a logo atual e para remover deve ser enviado o texto 'remover' ",
            "school": "iniciais da escola",
            "ead_id": "id do curso no moodle"
        }
    }    
    */
    public static function course_updated(\core\event\base $event) {
        global $DB, $CFG;
        $curso = $DB->get_record($event->objecttable,array('id'=>$event->objectid));
        
        $idnumber = $curso->idnumber;
        preg_match("/\_CH([0-9]+)/", $idnumber, $x);
        $ch = $x[1];

        $uri = 'https://escolamodelows.interlegis.leg.br/api/v1/cursos/atualizar';

        $obj = new StdClass();

        $camposCurso = array(
            "name" => $curso->fullname,
            "url" => "", // fixme não deve haver esse campo
            "course_load" => $ch, // fixme como obter esse campo no Moodle
            "description" => $curso->summary,
            "logo" => "", // fixme não deve ter esse campo
            "school" => "SSL", // fixme criar campo no moodle
            "ead_id" => $curso->id
        );

        $obj->school = $CFG->school;
        $obj->course = $camposCurso;
        $json = json_encode($obj);

        $response = \Httpful\Request::patch($uri)
            ->sendsJson()
            ->body($json) 
            ->send();
        error_log("Retorno é " . $response->code);

    }

    public static function course_deleted(\core\event\base $event) {
    
    
    }


    //
    // EVENTOS RELACIONADOS A MATRÍCULAS
    //

    //public static function course_updated(\core\event\base $event) {
    
    
    //}

}
