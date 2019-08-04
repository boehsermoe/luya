<?php

namespace luya\theme;

use luya\base\PackageConfig;
use luya\Exception;
use luya\helpers\Json;
use Yii;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Core theme manager for LUYA.
 *
 * This component manage available themes via file system and the actual display themes.
 *
 * @author Mateusz Szymański Teamwant <zzixxus@gmail.com>
 * @author Bennet Klarhölter <boehsermoe@me.com>
 * @since  1.1.0
 */
class ThemeManager extends \yii\base\Component
{
    const APP_THEMES_BLANK = '@app/themes/blank';
    
    /**
     * Name of the theme which should be activated on setup.
     *
     * @var string
     */
    public $activeThemeName;
    
    /**
     * @var ThemeConfig[]
     */
    private $_themes = [];
    
    /**
     * Read the theme.json and create a new \luya\theme\ThemeConfig for the given base path.
     *
     * @param string $basePath
     *
     * @return ThemeConfig
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected static function loadThemeConfig(string $basePath): ThemeConfig
    {
        if (strpos($basePath, '@') === 0) {
            $dir = Yii::getAlias($basePath);
        } elseif (strpos($basePath, '/') !== 0) {
            $dir = $basePath = Yii::$app->basePath . DIRECTORY_SEPARATOR . $basePath;
        }
        
        if (!is_dir($dir) || !is_readable($dir)) {
            throw new Exception('Theme directory not exists or readable: ' . $dir);
        }
        
        $themeFile = $dir . '/theme.json';
        if (file_exists($themeFile)) {
            $config = Json::decode(file_get_contents($themeFile)) ?: [];
        } else {
            $config = [];
        }
        
        $themeConfig = new ThemeConfig($basePath, $config);
        
        return $themeConfig;
    }
    
    /**
     * Setup active theme
     */
    final public function setup()
    {
        if ($this->getActiveTheme() instanceof Theme) {
            // Active theme already loaded
            return;
        }
        
        $basePath = $this->getActiveThemeBasePath();
        
        try {
            $this->beforeSetup($basePath);
            
            $themeConfig = $this->getThemeByBasePath($basePath);
            $theme = new Theme($themeConfig);
            $this->activate($theme);
        } catch (InvalidArgumentException $ex) {
            Yii::error($ex->getMessage(), 'luya-theme');
        }
    }
    
    /**
     * @param string $basePath
     */
    protected function beforeSetup(string &$basePath)
    {
        $event = new Event();
        $event->data = ['basePath' => $basePath];
        $this->trigger('setup', $event);
        
        $basePath = $event->data['basePath'];
    }
    
    /**
     * Get base path of active theme.
     *
     * @return string
     * @throws \yii\db\Exception
     */
    protected function getActiveThemeBasePath()
    {
        if (!empty($this->activeThemeName) && is_string($this->activeThemeName)) {
            return $this->activeThemeName;
        }
        
        return self::APP_THEMES_BLANK;
    }
    
    /**
     * Get all theme configs as array list.
     *
     * @return ThemeConfig[]
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function getThemes()
    {
        if ($this->_themes) {
            return $this->_themes;
        }
        
        $themeDefinitions = $this->getThemeDefinitions();
        
        foreach ($themeDefinitions as $themeDefinition) {
            $themeConfig = static::loadThemeConfig($themeDefinition);
            $this->registerTheme($themeConfig);
        }
        
        return $this->_themes;
    }
    
    /**
     * Get theme definitions by search in `@app/themes` and the `Yii::$app->getPackageInstaller()`
     *
     * @return string[]
     */
    protected function getThemeDefinitions(): array
    {
        $themeDefinitions = [];
        
        if (file_exists(Yii::getAlias('@app/themes'))) {
            foreach (scandir(Yii::getAlias('@app/themes')) as $dirPath) {
                $themeDefinitions[] = "@app/themes/" . basename($dirPath);
            }
        }
        
        foreach (Yii::$app->getPackageInstaller()->getConfigs() as $config) {
            /** @var PackageConfig $config */
            $themeDefinitions = array_merge($themeDefinitions, $config->themes);
        }
        
        return $themeDefinitions;
    }
    
    public function getThemeByBasePath($basePath)
    {
        $themes = $this->getThemes();
        
        if (!isset($themes[$basePath])) {
            throw new InvalidArgumentException("Theme $basePath could not loaded.");
        }
        
        return $themes[$basePath];
    }
    
    /**
     * Register a theme config and set the path alias with the name of the theme.
     *
     * @param ThemeConfig $themeConfig Base path of the theme.
     *
     * @throws InvalidConfigException
     */
    protected function registerTheme(ThemeConfig $themeConfig)
    {
        if (isset($this->_themes[$themeConfig->getBasePath()])) {
            throw new InvalidArgumentException("Theme already registered.");
        }
        
        $this->_themes[$themeConfig->getBasePath()] = $themeConfig;
        
        Yii::setAlias('@' . basename($themeConfig->getBasePath()) . 'Theme', $themeConfig->getBasePath());
    }
    
    /**
     * Change the active theme in the \yii\base\View component and set the `activeTheme ` alias to new theme base path.
     *
     * @param Theme $theme
     */
    protected function activate(Theme $theme)
    {
        Yii::$app->view->theme = $theme;
        Yii::setAlias('activeTheme', $theme->basePath);
        
        $this->setActiveTheme($theme);
    }
    
    /**
     * @var Theme|null
     */
    private $_activeTheme;
    
    /**
     * The active theme. Is null if no theme activated.
     *
     * @var Theme|null
     */
    public function getActiveTheme()
    {
        return $this->_activeTheme;
    }
    
    protected function setActiveTheme(Theme $theme)
    {
        $this->_activeTheme = $theme;
    }
}
