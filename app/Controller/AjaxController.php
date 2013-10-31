<?php
class AjaxController extends AppController {
	var $uses = array('Game', 'Dungeon', 'Classe', 'Race', 'EventsCharacter', 'Character', 'Event');

    function beforeFilter() {
        parent::beforeFilter();

        Configure::write('debug', 0);
        $this->layout = 'ajax';
		$this->autoRender = false;
    }

    function getListByGame() {
    	if(!empty($this->request->query['game'])) {
    		$gameId = $this->request->query['game'];

	    	$classesList = $this->Classe->find('list', array('conditions' => array('game_id' => $gameId), 'order' => 'title ASC'));
	        $this->set('classesList', $classesList);

	        $racesList = $this->Race->find('list', array('conditions' => array('game_id' => $gameId), 'order' => 'title ASC'));
	        $this->set('racesList', $racesList);

	    	$this->render('/Elements/char_form_elements');
    	}

        return;
    }

    function getDungeonsByGame() {
        if(!empty($this->request->query['game'])) {
            $gameId = $this->request->query['game'];

            $dungeonsList = $this->Dungeon->find('all', array('fields' => array('id', 'title'), 'recursive' => -1, 'conditions' => array('game_id' => $gameId), 'order' => 'title ASC'));
            return json_encode($dungeonsList);
        }
    }

    function eventSignin() {
        $jsonMessage = array();
        if(!empty($this->request->query['u']) && !empty($this->request->query['e']) && isset($this->request->query['signin']) && !empty($this->request->query['character'])) {
            // Choosed character must be in the event level range
            $params = array();
            $params['fields'] = array('character_level');
            $params['recursive'] = '-1';
            $params['conditions']['id'] = $this->request->query['e'];
            if(!$event = $this->Event->find('first', $params)) {
                $jsonMessage['type'] = 'important';
                $jsonMessage['msg'] = __('MushRaider can\'t find this event');
                return json_encode($jsonMessage);
            }

            $params = array();
            $params['fields'] = array('level');
            $params['recursive'] = '-1';
            $params['conditions']['id'] = $this->request->query['character'];
            $params['conditions']['user_id'] = $this->user['User']['id'];
            $params['conditions']['level >='] = $event['Event']['character_level'];
            if(!$character = $this->Character->find('first', $params)) {
                $jsonMessage['type'] = 'important';
                $jsonMessage['msg'] = __('Your character mush be above the level %s', $event['Event']['character_level']);
                return json_encode($jsonMessage);
            }

            $toSave = array();
            $toSave['event_id'] = $this->request->query['e'];
            $toSave['user_id'] = $this->request->query['u'];
            $toSave['character_id'] = !empty($this->request->query['character'])?$this->request->query['character']:null;
            $toSave['raids_role_id'] = !empty($this->request->query['role'])?$this->request->query['role']:null;
            $toSave['comment'] = trim(strip_tags($this->request->query['c']));
            $toSave['status'] = $this->request->query['signin'];
            if($this->EventsCharacter->__add($toSave)) {
                $jsonMessage['type'] = $toSave['status']?'info':'warning';
                $jsonMessage['msg'] = 'ok';
            }else {
                $jsonMessage['type'] = 'important';
                $jsonMessage['msg'] = __('Error while adding your character');
            }
        }

        return json_encode($jsonMessage);
    }

    function roster() {
        if(isset($this->request->query['v']) && !empty($this->request->query['r']) && !empty($this->request->query['e'])) {
            $eventId = $this->request->query['e'];
            $roleId = str_replace('role_', '', $this->request->query['r']);
            $validatedList = explode(',', $this->request->query['v']);

            $params = array();
            $params['fields'] = array('id', 'character_id');
            $params['recursive'] = -1;
            $params['conditions']['event_id'] = $eventId;
            $params['conditions']['raids_role_id'] = $roleId;
            $params['conditions']['status >'] = 0;
            if($eventCharacters = $this->EventsCharacter->find('all', $params)) {
                foreach($eventCharacters as $eventCharacter) {
                    $toSave = array();
                    $toSave['id'] = $eventCharacter['EventsCharacter']['id'];
                    $toSave['status'] = in_array($eventCharacter['EventsCharacter']['character_id'], $validatedList)?2:1;
                    if(!$this->EventsCharacter->save($toSave)) {
                        return 'fail';
                        prd($this->EventsCharacter->validationErrors);
                    }
                }

                return 'ok';
            }
        }

        return 'fail';
    }
}