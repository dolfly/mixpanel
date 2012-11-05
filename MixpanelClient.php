<?php

require_once "Browser.php";

/**
 * Sends events to Mixpanel API. It uses mixpanel cookie from the browser to identify the user if it can
 */
class MixpanelClient {

    private $host;
    private $token;
    private $distinct_id;

    // Mixpanel Constants
    const DISTINCT_ID = 'distinct_id';
    const TOKEN = 'token';
    const IP = 'ip';
    const REFERRER = '$referrer';
    const REFERRING_DOMAIN = '$referring_domain';
    const INITIAL_REFERRER = '$initial_referrer';
    const INITIAL_REFERRING_DOMAIN = '$initial_referring_domain';
    const BROWSER = '$browser';
    const OS = '$os';
    const DEVICE = '$device';
    const DIRECT = '$direct';
    const ANDROID_MOBILE = "Android Mobile";
    const MOBILE_SAFARI = "Mobile Safari";
    const MAC_OS_X = "Mac OS X";
    const ANDROID = "Android";
    const BLACK_BERRY = "BlackBerry";
    const WINDOWS_PHONE = "Windows Phone";
    const IPAD = "iPad";
    const IPHONE = "iPhone";
    const IPOD = "iPod";

    /**
     * @var Browser
     */
    private $browser;

    private $data;

    private static $_instance;

    /**
     * @static
     * @return MixpanelClient
     */
    public static function getInstance() {
        if(self::$_instance == null) {
            $client = new MixpanelClient();
            $client->host = $GLOBALS["__MIXPANEL_HOST"];
            $client->token = $GLOBALS["__MIXPANEL_TOKEN"];
            $client->browser = new Browser();
            self::$_instance = $client;
        }
        return self::$_instance;
    }

    public function setCookie($cookie) {
        $mixpanelCookieName = "mp_{$this->token}_mixpanel";
        if(!isset($cookie[$mixpanelCookieName])) {
            return;
        }
        $jsonString = $cookie[$mixpanelCookieName];
        $this->data = json_decode($jsonString);
        if(isset($this->data->distinct_id)) {
            $this->distinct_id = $this->data->distinct_id;
        }
    }

    public function track($event, $properties = array()) {
        $this->addDefaultProperties($properties);
        if($this->data) {
            foreach($this->data as $key => $value) {
                $properties[$key] = $value;
            }
        }

        $params = array(
            "event" => $event,
            "properties" => $properties
        );
        $data = base64_encode(json_encode($params));
        $url = $this->host."/track?data=".$data;
        exec("curl '" . $url . "' >/dev/null 2>&1 &");
    }

    public function setDistinctId($distinct_id)
    {
        $this->distinct_id = $distinct_id;
    }

    public function getDistinctId()
    {
        return $this->distinct_id;
    }

    protected function addDefaultProperties(array &$properties) {
        // Special Mixpanel Properties
        $properties[self::DISTINCT_ID] = $this->distinct_id;
        $properties[self::TOKEN] = $this->token;
        $properties[self::IP] = IPUtil::getClientIp();

        // Properties from JS Api
        if ($this->getReferrer()) {
            $properties[self::REFERRER] = $this->getReferrer();
            $properties[self::REFERRING_DOMAIN] = $this->getReferringDomain();
        }

        if ($this->getInitialReferrer())
            $properties[self::INITIAL_REFERRER] = $this->getInitialReferrer();
        if ($this->getInitialReferringDomain())
            $properties[self::INITIAL_REFERRING_DOMAIN] = $this->getInitialReferringDomain();

        $properties[self::BROWSER] = $this->getBrowser();
        $properties[self::OS] = $this->getPlatform();
        $device = $this->getDevice();
        if($device) {
            $properties[self::DEVICE] = $this->getDevice();
        }
    }

    protected function getReferrer() {
        return isset($_SERVER["HTTP_REFERER"]) && trim($_SERVER['HTTP_REFERER']) != '' ? $_SERVER["HTTP_REFERER"] : self::DIRECT;
    }

    protected function getReferringDomain() {
        $referrer = $this->getReferrer();
        if(!$referrer) {
            return null;
        } else if ($referrer === self::DIRECT) {
            return $referrer;
        }
        $uri = parse_url($referrer);
        return $uri["host"];
    }

    protected function getInitialReferrer() {
        return isset($this->data->{self::INITIAL_REFERRER}) ? $this->data->{self::INITIAL_REFERRER} : null;
    }

    protected function getInitialReferringDomain() {
        return isset($this->data->{self::INITIAL_REFERRING_DOMAIN}) ? $this->data->{self::INITIAL_REFERRING_DOMAIN} : null;
    }

    protected function getBrowser() {
        $platform = $this->browser->getPlatform();
        if($platform == Browser::PLATFORM_BLACKBERRY) {
            return Browser::PLATFORM_BLACKBERRY;
        }
        $browser = $this->browser->getBrowser();
        switch ($browser) {
            case Browser::BROWSER_ANDROID:
                return self::ANDROID_MOBILE;
            case Browser::BROWSER_IPAD:
            case Browser::BROWSER_IPHONE:
            case Browser::BROWSER_IPOD:
                return self::MOBILE_SAFARI;
            default:
                return $browser;
        }
    }

    protected function getPlatform() {
        $platform = $this->browser->getPlatform();
        switch ($platform) {
            case Browser::PLATFORM_APPLE:
            case Browser::PLATFORM_IPAD:
            case Browser::PLATFORM_IPHONE:
            case Browser::PLATFORM_IPOD:
                return self::MAC_OS_X;
            default:
                return $platform;
        }
    }

    protected function getDevice() {
        $browser = $this->browser->getBrowser();
        $platform = $this->browser->getPlatform();
        if ($browser === Browser::BROWSER_ANDROID || $platform === Browser::PLATFORM_ANDROID) {
            return self::ANDROID;
        }
        if ($browser === Browser::BROWSER_BLACKBERRY || $platform === Browser::PLATFORM_BLACKBERRY) {
            return self::BLACK_BERRY;
        }
        if ($browser === Browser::BROWSER_POCKET_IE) {
            return self::WINDOWS_PHONE;
        }
        if ($browser === Browser::BROWSER_IPAD) {
            return self::IPAD;
        }
        if ($browser === Browser::BROWSER_IPHONE) {
            return self::IPHONE;
        }
        if ($browser === Browser::BROWSER_IPOD) {
            return self::IPOD;
        }
        return null;
    }

}
