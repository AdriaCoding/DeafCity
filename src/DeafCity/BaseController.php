<?php

namespace DeafCity;

class BaseController {
    protected $app;
    
    protected $security_token;
    
    public function __construct($app) {
        $this->app = $app;
    }
    
    public function view($viewName, $data, $layout=false) {
        extract($data);
        if (!empty($layout)) {
            ob_start();
        }
        include(ROOTDIR . '/views/' . $viewName . '.php');
        if (!empty($layout)) {
            $content = ob_get_clean();
            $this->view($layout, [
                'content' => $content
            ]);
        }
    }
    
    public function securityToken() {
        if (!isset($this->security_token)) {
            $this->security_token = md5(mt_rand());
            $_SESSION['security_token'] = $this->security_token;
        }
        return $this->security_token;
    }
}
