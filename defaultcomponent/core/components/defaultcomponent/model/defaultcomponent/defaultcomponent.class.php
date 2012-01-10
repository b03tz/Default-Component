<?php
/**
 * defaultComponent
 *
 * Copyright 2011-12 by SCHERP Ontwikkeling <info@scherpontwikkeling.nl>
 *
 * This file is part of defaultComponent.
 *
 * defaultComponent is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * defaultComponent is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * defaultComponent; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package defaultComponent
 */
/**
 * This file is the main class file for defaultComponent.
 *
 * @copyright Copyright (C) 2011, SCHERP Ontwikkeling <info@scherpontwikkeling.nl>
 * @author SCHERP Ontwikkeling <info@scherpontwikkeling.nl>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @package defaultcomponent
 */
class defaultComponent {
    /**
     * A reference to the modX object.
     * @var modX $modx
     */
    public $modx = null;
    /**
     * The request object for the current state
     * @var defaultComponentControllerRequest $request
     */
    public $request;
    /**
     * The controller for the current request
     * @var defaultComponentController $controller
     */
    public $controller = null;

    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;

        /* allows you to set paths in different environments
         * this allows for easier SVN management of files
         */
        $corePath = $this->modx->getOption('defaultcomponent.core_path',null,$modx->getOption('core_path').'components/defaultcomponent/');
        $assetsPath = $this->modx->getOption('defaultcomponent.assets_path',null,$modx->getOption('assets_path').'components/defaultcomponent/');
        $assetsUrl = $this->modx->getOption('defaultcomponent.assets_url',null,$modx->getOption('assets_url').'components/defaultcomponent/');

        $this->config = array_merge(array(
            'corePath' => $corePath,
            'modelPath' => $corePath.'model/',
            'processorsPath' => $corePath.'processors/',
            'controllersPath' => $corePath.'controllers/',
            'chunksPath' => $corePath.'elements/chunks/',
            'snippetsPath' => $corePath.'elements/snippets/',

            'baseUrl' => $assetsUrl,
            'cssUrl' => $assetsUrl.'css/',
            'jsUrl' => $assetsUrl.'js/',
            'connectorUrl' => $assetsUrl.'connector.php',

            'thread' => '',

            'tpldefaultcomponentAddComment' => '',
            'tpldefaultcomponentComment' => '',
            'tpldefaultcomponentCommentOptions' => '',
            'tpldefaultcomponentComments' => '',
            'tpldefaultcomponentLoginToComment' => '',
            'tpldefaultcomponentReport' => '',
        ),$config);

        $this->modx->addPackage('defaultcomponent',$this->config['modelPath']);
        
        if ($this->modx->lexicon) {
            $this->modx->lexicon->load('defaultcomponent:default');
        }
    }

    /**
     * Initializes defaultComponent based on a specific context.
     *
     * @access public
     * @param string $ctx The context to initialize in.
     * @return string The processed content.
     */
    public function initialize($ctx = 'mgr') {
        $output = '';
        switch ($ctx) {
            case 'mgr':
                if (!$this->modx->loadClass('defaultcomponent.request.defaultComponentControllerRequest',$this->config['modelPath'],true,true)) {
                    return 'Could not load controller request handler.';
                }
                $this->request = new defaultComponentControllerRequest($this);
                $output = $this->request->handleRequest();
                break;
        }
        return $output;
    }
    
    /**
     * Load the appropriate controller
     * @param string $controller
     * @return null|defaultComponentController
     */
    public function loadController($controller) {
        if ($this->modx->loadClass('defaultComponentController',$this->config['modelPath'].'defaultcomponent/request/',true,true)) {
            $classPath = $this->config['controllersPath'].'web/'.$controller.'.php';
            $className = 'defaultComponent'.$controller.'Controller';
            
            if (file_exists($classPath)) {
                if (!class_exists($className)) {
                    $className = require_once $classPath;
                }
                if (class_exists($className)) {
                    $this->controller = new $className($this,$this->config);
                } else {
                    $this->modx->log(modX::LOG_LEVEL_ERROR,'[defaultComponent] Could not load controller: '.$className.' at '.$classPath);
                }
            } else {
                $this->modx->log(modX::LOG_LEVEL_ERROR,'[defaultComponent] Could not load controller file: '.$classPath);
            }
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[defaultComponent] Could not load defaultcomponentController class.');
        }
        return $this->controller;
    }
    
    public function trackRequest($data) {
    	$visitor = $this->modx->getObject('defaultComponentVisitor', array(
    		'ip' => $_SERVER['REMOTE_ADDR']
    	));
    	
    	// Parse the current URL
    	$data['url'] = str_replace('http://', '', $data['url']);
    	$data['url'] = explode('/', $data['url']);
    	array_shift($data['url']);
    	$data['url'] = '/'.implode('/', $data['url']);
    	
    	$createNewVisitor = true;

    	if ($visitor != null) {
    		// Check if visitor is still active
    		if ((time() - $visitor->get('last_click')) < (int) $this->modx->getOption('defaultcomponent.visitor_timeout')) {
    			$visitor->set('current_url', $data['url']);
				$visitor->set('last_click', time());	
				$createNewVisitor = false;
    		} else {
    			// Session has expired, remove from database
    			$visitor->remove();	
    		}
    	}
    	
    	if ($createNewVisitor) {
    		$visitor = $this->modx->newObject('defaultComponentVisitor', array(
    			'ip' => $_SERVER['REMOTE_ADDR'],
    			'current_url' => $data['url'],
    			'visit_start' => time(),
    			'last_click' => time(),
    			'data' => array(
    				'browser' => ucfirst($data['browser']),
    				'os' => ucfirst($data['os']),
    				'screenresolution' => $data['screenresolution']
    			),
    			'data_remote' => array(
    				'u_invite' => 0,
    				'u_istyping' => 0,
    				'a_invite' => 0,
    				'a_istyping' => 0,
    				'inchat' => 0
    			)
    		));	
    	}
    	
    	return $visitor->save();
    }
    
    public function getRemoteData() {
    	$visitor = $this->modx->getObject('defaultComponentVisitor', array(
    		'ip' => $_SERVER['REMOTE_ADDR']
    	));
    	
    	if ($visitor != null) {
    		return json_encode(array_merge(
    			$visitor->get('data_remote'),
    			array(
    				'success' => true
    			)
    		));	
    	} else {
    		return json_encode(array(
    			'success' => false
    		));	
    	}
    }
}