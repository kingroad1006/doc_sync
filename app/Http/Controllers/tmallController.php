<?php

namespace App\Http\Controllers;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;

class tmallController extends Controller
{
    protected $login_url = 'https://login.taobao.com/member/login.jhtml';
    protected $login_required_url = 'https://member1.taobao.com/member/fresh/web_wangwang.htm';
    protected $username = '13241984227';
    protected $password = 'wl19891006';
    protected $host = 'http://localhost:4444';
    protected $cookie_file;

    public function __construct()
    {
        $this->cookie_file = storage_path('taobao_cookies.key');
    }

    /**
     * @var RemoteWebDriver
     */
    protected $driver;

    /**
     * @var ChromeOptions
     */
    protected $chromeOptions;

    /**
     * @var ChromeDevToolsDriver
     */
    protected $devTools;

    /**
     * 初始化浏览器
     */
    protected function initChromeDriver()
    {
        // ChromeOptions
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments([
            'window-position=0,0',
            'window-size=1200,800',
            'disable-blink-features',
            'disable-blink-features=AutomationControlled',
        ]);
        $chromeOptions->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $chromeOptions->setExperimentalOption('profile.managed_default_content_settings.images', 2);
        $this->chromeOptions = $chromeOptions;

        // ChromeDriver
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $this->chromeOptions);
        $driver = RemoteWebDriver::create($this->host, $capabilities);
        $this->driver = $driver;

        // ChromeDevTools
        # 通过浏览器的 dev_tool 在 get 页面前将 .webdriver 属性改为 "undefined"
        $devTools = new ChromeDevToolsDriver($this->driver);
        $devTools->execute('Page.addScriptToEvaluateOnNewDocument', ['source' => 'Object.defineProperty(navigator, \'webdriver\', {get: () => undefined})']);
        $this->devTools = $devTools;
    }

    /**
     * 判断是否已经登录
     *
     * @param bool $first_time 是否是第一次判断，初次用 get()，后续用 navigate()
     * @return bool
     */
    protected function isLogin($first_time = false)
    {
        if ($first_time) {
            $this->driver->get($this->login_required_url);
        } else {
            $this->driver->navigate()->to($this->login_required_url);
        }

        return $this->login_required_url === $this->driver->getCurrentURL();
    }

    /**
     * 自动登录
     */
    public function autoLogin()
    {
        $this->initChromeDriver();

        $isLogin = $this->isLogin(true);
        if (!$isLogin) {
            // 检查是否存在 cookies
            if (!file_exists($this->cookie_file)) {
                $this->login();
            }

            // cookies 存在则解析
            $cookies = unserialize(file_get_contents($this->cookie_file));
            foreach ($cookies as $cookie) {
                $this->driver->manage()->addCookie($cookie);
            }

            // 添加 cookies 后判断是否登录
            $isLogin = $this->isLogin();
            if (!$isLogin) {
                $this->login();
            }
        }
    }

    /**
     * 使用用户名密码登录
     */
    protected function login()
    {
        $this->driver->navigate()->to($this->login_url . '?redirectURL=' . urlencode($this->login_required_url));

        $this->driver->findElement(WebDriverBy::name('fm-login-id'))->sendKeys($this->username);
        $this->driver->findElement(WebDriverBy::name('fm-login-password'))->sendKeys($this->password);
        $this->driver->findElement(WebDriverBy::className('password-login'))->click();

        sleep(10);

        if ($this->login_required_url === $this->driver->getCurrentURL()) {
            // 登录成功后保存 cookies
            $cookies = $this->driver->manage()->getCookies();
            file_put_contents($this->cookie_file, serialize($cookies));
        } else {
            // 登录失败
            exit('淘宝：使用账号密码登录失败。');
        }
    }
}
