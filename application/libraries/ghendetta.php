<?php

class Ghendetta {
    
    private $ci;
    private $time_window = 604800;
    
    function __construct() {
        $this->ci = &get_instance();
    }
    
    function set_user($fsqid) {
        $cookie = array('name' => 'ghendetta_user', 'value' => $fsqid, 'expire' => '8640000');
        return $this->ci->input->set_cookie($cookie);
    }
    
    function current_user() {
        $fsqid = $this->ci->input->cookie('ghendetta_user', TRUE);
        
        // user not detected
        if (!$fsqid) {
            return FALSE;
        }
        
        $this->ci->load->model('user_model');
        $user = $this->ci->user_model->get($fsqid);
        
        // user or token not found
        if (!$user || !$user['token']) {
            return FALSE;
        }
        
        return $user;
    }
    
    function refresh_user($fsqid, $token = FALSE) {
        $this->ci->load->model('user_model');
        
        if (!$token) {
            if ($user = $this->ci->user_model->get($fsqid)) {
                // get token from database
                $token = $user['token'];
            } else if ($this->ci->foursquare->token()) {
                // get active foursquare token
                $token = $this->ci->foursquare->token();
            }
        }
        
        // still no token?
        if (!$token) {
            return FALSE;
        }
        
        $this->ci->foursquare->set_token($token);
        $user = $this->ci->foursquare->api('users/' . $fsqid);
        
        if (isset($user) && $user->meta->code == 200) {
            $data = array();
            $data['fsqid'] = $user->response->user->id;
            $data['firstname'] = $user->response->user->firstName;
            $data['lastname'] = isset($user->response->user->lastName) ? $user->response->user->lastName : '';
            $data['email'] = isset($user->response->user->contact->email) ? $user->response->user->contact->email : '';
            $data['picurl'] = isset($user->response->user->photo) ? $user->response->user->photo : '';
            $data['token'] = $token;
            
            if (!$this->ci->user_model->exists($fsqid)) {
                // TEMP
                $data['clanid'] = rand(1, 4);
                
                $this->ci->user_model->insert($data);
            } else {
                $this->ci->user_model->update($fsqid, $data);
            }
        } else {
            return FALSE;
        }
    }
    
    function refresh_checkins($fsqid, $token = FALSE) {
        $this->ci->load->model('user_model');
        
        if (!$token) {
            if ($user = $this->ci->user_model->get($fsqid)) {
                // get token from database
                $token = $user['token'];
            } else if ($this->ci->foursquare->token()) {
                // get active foursquare token
                $token = $this->ci->foursquare->token();
            }
        }
        
        // still no token?
        if (!$token) {
            return FALSE;
        }
        
        // load foursquare library and use this user's token
        $this->ci->foursquare->set_token($token);
        
        $this->ci->load->model('checkin_model');
        $this->ci->load->model('region_model');
        $this->ci->load->helper('polygon');
        
        $regions = $this->ci->region_model->get_all();
        
        // only get checkins in interval
        $checkins = $this->ci->foursquare->api('users/' . $fsqid . '/checkins', array('afterTimestamp' => (time() - $this->time_window)));
        
        if (isset($checkins) && $checkins->meta->code == 200) {
            foreach ($checkins->response->checkins->items as $checkin) {
                // only new checkins
                if (!$this->ci->checkin_model->exists($checkin->id)) {
                    
                    // check what region the checkin was located in
                    $found_region = FALSE;
                    foreach ($regions as $region) {
                        $lon = $checkin->venue->location->lng;
                        $lat = $checkin->venue->location->lat;
                        
                        if (is_in_polygon($region['coords'], $lon, $lat)) {
                            $found_region = $region;
                            break; // yes this is a break :)
                        }
                    }
                    
                    if ($found_region) {
                        $data = array();
                        $data['checkinid'] = $checkin->id;
                        $data['date'] = $checkin->createdAt;
                        $data['venueid'] = $checkin->venue->id;
                        $data['lon'] = $checkin->venue->location->lng;
                        $data['lat'] = $checkin->venue->location->lat;
                        $data['regionid'] = $found_region['regionid'];
                        $data['userid'] = $fsqid;
                        
                        $this->ci->checkin_model->insert($data);
                    }
                }
            }
        } else {
            return FALSE;
        }
    }

}