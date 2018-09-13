<?php

include('httpful.phar');

class block_courses_verification_observer {
    public static function created(\core\event\base $event) {
        global $DB, $CFG;
        $dados = $DB->get_record($event->objecttable,array('id'=>$event->objectid));
        $uri = 'http://localhost:3000/api/v1/courses/adicionar/';
        $response = \Httpful\Request::post($uri)
            ->sendsJson()
            ->body('{"course":{"name":"' . $dados->fullname . '","course_category_id":"' . "1" . '","ead_id":"' . $dados->id . '","school_id":"' . "1" . '","url":"' . "/course/view.php" . '","description":"' . $dados->summary . '"}}') // o json é {"curso":{"nome":"nome_curso"}}
            ->send();

    }
    public static function updated(\core\event\base $event) {
        global $DB, $CFG;
        $dados = $DB->get_record($event->objecttable,array('id'=>$event->objectid));
        $uri = 'http://localhost:3000/api/v1/courses/atualizar/';
        $response = \Httpful\Request::patch($uri)
            ->sendsJson()
            ->body('{"course":{
                                "name":"' . $dados->fullname . '",
                                "course_category_id":"' . "1" . '",
                                "ead_id":"' . $dados->id . '",
                                "school_id":"' . "1" . '",
                                "description:"' . $dados->summary . '"
                              }
            }') // o json é {"curso":{"nome":"nome_curso"}}
            ->send();
    }
}