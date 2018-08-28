<?php
$observers = array(
    array(
        'eventname' => '\core\event\course_created',
        'callback' => 'block_courses_verification_observer::created'
    ),
    array(
        'eventname'   => '\core\event\course_updated',
        'callback'    => 'block_courses_verification_observer::updated',
    )
);