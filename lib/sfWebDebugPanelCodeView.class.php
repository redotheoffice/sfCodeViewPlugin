<?php

/**
* sfWebDebugPanelCodeView
*/
class sfWebDebugPanelCodeView extends sfWebDebugPanel
{
  public function getTitle()
  {
    sfProjectConfiguration::getActive()->loadHelpers('Url');
    return '<form style="display:inline" action="'.url_for('sfCodeView').'"><input type="text" name="class" size="5"/></form>';
  }
 
  public function getPanelTitle()
  {
    return 'View php class code';
  }
  
  private function buildGroupedList($classes)
  {
    sfProjectConfiguration::getActive()->loadHelpers('Url');

    $coreList = "";
    $lastGroup = "";
    foreach ($classes as $className => $group)
    {
      if ($lastGroup != $group)
      {
        if (strlen($coreList)>0)
        {
          $coreList .= "</ul></li>";  
        }
        $coreList .= "<li>".$group."<ul>";
        $lastGroup = $group;
      }
      $coreList .= "<li>".$className."</li>";
    }

    return "<ul>".$coreList."</ul>";
  }
 
  public function getPanelContent()
  {
    $cacheKey = sfConfig::get('app_sfCodeViewPlugin_cache_in_user_session', false);
    $user = $cacheKey ? sfContext::getInstance()->getUser() : null;

    $content = "";
    
    if ($user && $user->hasAttribute($cacheKey))
    {
      $content = $user->getAttribute($cacheKey);
    }
    
    if (strlen($content) == 0)
    {
      $coreList = "";
      $userList = "";
      
      $libDir = sfConfig::get("sf_symfony_lib_dir");
      $files = sfFinder::type('file')
        ->prune('plugins')
        ->prune('vendor')
        ->prune('skeleton')
        ->prune('default')
        ->name('*\.class\.php')
        ->in($libDir)
      ;    
      sort($files, SORT_STRING);
      
      $classes = array();
      foreach ($files as $file)
      {
        $group = substr(str_replace(DIRECTORY_SEPARATOR, '/', dirname($file)),strlen($libDir)+1);
        $className = basename($file, '.class.php');
        $link = link_to($className, 'sfCodeView', array('class'=>$className));
        $classes[$link] = $group;
      }
      
      $coreList = $this->buildGroupedList($classes);
      
      //build user list
      
      $rootDir = sfConfig::get("sf_root_dir");
      $fileFinder = sfFinder::type('file')
        ->ignore_version_control()
        ->prune('cache')
        ->name('*\.php')
        ->sort_by_name();
      if (!sfConfig::get('app_sfCodeViewPlugin_recurse_vendor_dirs', false))
      {
        $fileFinder->prune('vendor');
      }
      $files = $fileFinder->in($rootDir);
      
      $classes = array();
      $matches = array();
      foreach ($files as $file)
      {
        $group = substr(str_replace(DIRECTORY_SEPARATOR, '/', dirname($file)),strlen($rootDir)+1);
        
        preg_match_all('~^\s*(?:abstract\s+|final\s+)?(?:class|interface)\s+(\w+)~mi', file_get_contents($file), $matches);
        foreach ($matches[1] as $className)
        {
          $fileName = "";
          if ($className.'.class.php' != basename($file))
          {
            $fileName = ' ('.basename($file).')';
          }
          $link = link_to($className.$fileName, 'sfCodeView', array('class'=>$className));
          $classes[$link] = $group;
        }

      }

      $userList = $this->buildGroupedList($classes);
  
      $content = '<div id="sfCodeViewPluginContent">'.
              '<i>Enter the name of a class you want to view in the toolbar and hit \'enter\' to view its code</i>, or click one of the available classes below.'.
              '<div style="float: left; width:45%;"><h2>User classes</h2>'.$userList.'</div>'.
              '<div style="float: left; width:45%;"><h2>Symfony classes</h2>'.$coreList.'</div>'.
              '</div>';
    }

    if ($user)
    {
      $user->setAttribute($cacheKey, $content);
    }

    return $content;
  }
}
