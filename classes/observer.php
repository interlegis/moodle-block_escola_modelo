<?php

include('httpful.phar');

class block_escola_modelo_observer {
    
    //
    // EVENTOS RELACIONADOS A CURSOS
    //

    public static function course_created(\core\event\base $event) {
        global $DB, $CFG;
        $dados = $DB->get_record($event->objecttable,array('id'=>$event->objectid));
        $uri = 'http://localhost:3000/api/v1/courses/adicionar/';
        $response = \Httpful\Request::post($uri)
            ->sendsJson()
            ->body('{"course":{"name":"' . $dados->fullname . '","course_category_id":"' . "1" . '","ead_id":"' . $dados->id . '","school_id":"' . "1" . '","url":"' . "/course/view.php" . '","description":"' . $dados->summary . '"}}') // o json é {"curso":{"nome":"nome_curso"}}
            ->send();

    }

    public static function course_updated(\core\event\base $event) {
        global $DB, $CFG;
        $dados = $DB->get_record($event->objecttable,array('id'=>$event->objectid));
        
        //echo "ID é " . $dados->id;
        $uri = 'https://escolamodelows.interlegis.leg.br/api/v1/cursos/atualizar';
        $response = \Httpful\Request::patch($uri)
            ->sendsJson()
            ->body('{"course":{
                                "id":"1",
                                "name":"' . $dados->fullname . '",
                                "course_category_id":"' . "1" . '"
                              }
            }') // o json é {"curso":{"nome":"nome_curso"}}
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
