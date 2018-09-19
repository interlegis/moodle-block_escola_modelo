<?php
$observers = array(
    // Eventos de cursos
    array(
        'eventname' => '\core\event\course_created',
        'callback' => 'block_escola_modelo_observer::course_created'
    ),
    array(
        'eventname'   => '\core\event\course_updated',
        'callback'    => 'block_escola_modelo_observer::course_updated'
    ),
    array(
        'eventname'   => '\core\event\course_deleted',
        'callback'    => 'block_escola_modelo_observer::course_deleted'
    ),
    // Eventos de matrícula a cursos
    array(
        'eventname'   => 'core\event\user_enrolment_created',
        'callback'    => 'block_escola_modelo_observer::user_enrolment_created'
    ),
    array(
        'eventname'   => 'core\event\user_enrolment_deleted',
        'callback'    => 'block_escola_modelo_observer::user_enrolment_deleted'
    ),
    array(
        'eventname'   => 'core\event\user_enrolment_updated',
        'callback'    => 'block_escola_modelo_observer::user_enrolment_updated'
    )
);
