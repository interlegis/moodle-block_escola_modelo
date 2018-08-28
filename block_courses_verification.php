<?php
defined('MOODLE_INTERNAL') || die();

class block_courses_verification extends block_base
{
    public function init() {
        $this->title = get_string('courses_verification', 'block_courses_verification');
    }
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         =  new stdClass;
        $this->content->text   = 'The content of our block!';
        //$url = new moodle_url('/blocks/article/new.php');
        //$url_index = new moodle_url('/blocks/article/index.php');

        return $this->content;
    }
}