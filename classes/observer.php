<?php

include('httpful.phar');

class block_courses_verification_observer {
    public static function created(\core\event\base $event) {
        global $DB, $CFG;
        $dados = $DB->get_record($event->objecttable,array('id'=>$event->objectid));
        $uri = 'http://localhost:3000/api/v1/courses/add_course/';
        $response = \Httpful\Request::post($uri)
            ->sendsJson()
            ->body('{"course":{"name":"' . $dados->fullname . '"}}') // o json é {"curso":{"nome":"nome_curso"}}
            ->send();

    }
    public static function updated(\core\event\base $event) {
        global $DB, $CFG;
        $dados = new stdClass();
        $dados->article_title='TesteCurso';
        $dados->article_text='TesteCurso';
        $DB->insert_record('block_article', $dados);
    }
}