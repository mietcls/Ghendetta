<?php
/**
 * @copyright (C) 2012 by iRail vzw/asbl
 * @license AGPLv3
 * @author Jens Segers <jens at iRail.be>
 * @author Hannes Van De Vreken <hannes at iRail.be>
 */

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Clan extends CI_Controller {
    
    function index() {
        if ($user = $this->ghendetta->current_user()) {
            $this->load->model('clan_model');
            $members = $this->clan_model->get_members($user['clanid']);
            $clan = $this->clan_model->get_stats($user['clanid']);
            
            $this->load->view('clan', array('members' => $members, 'clan' => $clan, 'user' => $user));
        } else {
            redirect();
        }
    }

}