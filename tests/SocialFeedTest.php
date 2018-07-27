<?php
namespace SilverstripeSocialFeed\Tests;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use SilverstripeSocialFeed\Provider\FacebookProvider;
use SilverstripeSocialFeed\Provider\TwitterProvider;
use SilverstripeSocialFeed\Provider\InstagramProvider;
use SilverStripe\Dev\SapphireTest;

/**
 * SocialFeedProvider test
 * TODO: cache,uncached retrieval test, a fixture, a way to get a test feed for each service without having to get it approved
 */
class SocialFeedTest extends SapphireTest {

    protected $usesDatabase = false;

    public function setUp() {
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function testLinkReplace() {

        // input content - note \s prior to another_path
        $content = "<p>The <strong>quick brown fox</strong> at the website "
                    . "https://example.com/a_path/?foo=bar "
                    . "jumps over the lazy dog sleeping at "
                    . "https://example.com:80/ another_path/?foo=bar"
                    . "</p>";

        // stripped of HTML but with converted links from the URLs
        $expected_stripped = "The quick brown fox at the website "
                    . "<a href=\"https://example.com/a_path/?foo=bar\" target=\"_blank\">https://example.com/a_path/?foo=bar</a> "
                    . "jumps over the lazy dog sleeping at "
                    . "<a href=\"https://example.com:80/\" target=\"_blank\">https://example.com:80/</a> another_path/?foo=bar";

        // unstripped of HTML and with converted links from the URLs
        $expected_unstripped = "<p>The <strong>quick brown fox</strong> at the website "
                    . "<a href=\"https://example.com/a_path/?foo=bar\" target=\"_blank\">https://example.com/a_path/?foo=bar</a> "
                    . "jumps over the lazy dog sleeping at "
                    . "<a href=\"https://example.com:80/\" target=\"_blank\">https://example.com:80/</a> another_path/?foo=bar"
                    . "</p>";

        $feed = new SocialFeedProvider();

        $result = $feed->processTextContent($content, true);
        $result_string = $result->__toString();
        $this->assertEquals($result_string, $expected_stripped, "Stripped result doesn't match what is expected");

        $result = $feed->processTextContent($content, false);
        $result_string = $result->__toString();
        $this->assertEquals($result_string, $expected_unstripped, "Unstripped result doesn't match what is expected");

    }

    public function testGetTwitterProvider()
    {
      $provider = SocialFeedProvider::getProvider('SilverstripeSocialFeed\Provider\TwitterProvider', null);
      $provider_instance = $provider ? $provider->first() : false;
      $this->assertTrue( $provider_instance && $provider_instance instanceof TwitterProvider);
    }

    public function testGetInstagramProvider()
    {
      $provider = SocialFeedProvider::getProvider('SilverstripeSocialFeed\Provider\InstagramProvider', null);
      $provider_instance = $provider ? $provider->first() : false;
      $this->assertTrue( $provider_instance && $provider_instance instanceof InstagramProvider);
    }

    public function testGetFacebookProvider()
    {
      $provider = SocialFeedProvider::getProvider('SilverstripeSocialFeed\Provider\FacebookProvider', null);
      $provider_instance = $provider ? $provider->first() : false;
      $this->assertTrue( $provider_instance && $provider_instance instanceof FacebookProvider);
    }

}
