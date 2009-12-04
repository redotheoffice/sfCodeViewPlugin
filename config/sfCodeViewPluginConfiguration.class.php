<?php

/**
* sfCodeViewPluginConfiguration class
*/
class sfCodeViewPluginConfiguration extends sfPluginConfiguration
{
  public function configure()
  {
    $this->dispatcher->connect('debug.web.load_panels', array($this, 'configureWebDebugToolbar'));
  }
 
  public function configureWebDebugToolbar(sfEvent $event)
  {
    $webDebugToolbar = $event->getSubject();
    
    $webDebugToolbar->setPanel('codeview', new sfWebDebugPanelCodeView($webDebugToolbar));
  }
}
