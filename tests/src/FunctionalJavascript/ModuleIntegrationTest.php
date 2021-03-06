<?php

namespace Drupal\Tests\thunder\FunctionalJavascript;

use Behat\Mink\Element\DocumentElement;

/**
 * Testing of module integrations.
 *
 * @group Thunder
 *
 * @package Drupal\Tests\thunder\FunctionalJavascript
 */
class ModuleIntegrationTest extends ThunderJavascriptTestBase {

  use ThunderParagraphsTestTrait;
  use ThunderArticleTestTrait;
  use ThunderMetaTagTrait;

  /**
   * Column in diff table used for previous text.
   *
   * @var int
   */
  protected static $previousTextColumn = 3;

  /**
   * Column in diff table used for new text.
   *
   * @var int
   */
  protected static $newTextColumn = 6;

  /**
   * Validate diff entry for one field.
   *
   * @param string $fieldName
   *   Human defined field name.
   * @param array $previous
   *   Associative array with previous text per row.
   * @param array $previousHighlighted
   *   Previous highlighted texts.
   * @param array $new
   *   Associative array with new text per row.
   * @param array $newHighlighted
   *   New highlighted texts.
   */
  protected function validateDiff($fieldName, array $previous = [], array $previousHighlighted = [], array $new = [], array $newHighlighted = []) {
    // Check for old Text.
    $this->checkFullText($fieldName, static::$previousTextColumn, $previous);

    // Check for new Text.
    $this->checkFullText($fieldName, static::$newTextColumn, $new);

    // Check for highlighted Deleted text.
    $this->checkHighlightedText($fieldName, static::$previousTextColumn, $previousHighlighted);

    // Check for highlighted Added text.
    $this->checkHighlightedText($fieldName, static::$newTextColumn, $newHighlighted);
  }

  /**
   * Check full text in column defined by index.
   *
   * @param string $fieldName
   *   Human defined field name.
   * @param int $columnIndex
   *   Index of column in diff table that should be used to check.
   * @param array $textRows
   *   Associative array with text per row.
   */
  protected function checkFullText($fieldName, $columnIndex, array $textRows = []) {
    $page = $this->getSession()->getPage();

    foreach ($textRows as $indexRow => $expectedText) {
      $previousText = $page->find('xpath', "//tr[./td[text()=\"{$fieldName}\"]]/following-sibling::tr[{$indexRow}]/td[{$columnIndex}]")
        ->getText();

      $this->assertEquals($expectedText, htmlspecialchars_decode($previousText, ENT_QUOTES | ENT_HTML401));
    }
  }

  /**
   * Check more highlighted text in rows.
   *
   * @param string $fieldName
   *   Human defined field name.
   * @param int $columnIndex
   *   Index of column in diff table that should be used to check.
   * @param array $highlightedTextRows
   *   New highlighted texts per row.
   */
  protected function checkHighlightedText($fieldName, $columnIndex, array $highlightedTextRows) {
    $page = $this->getSession()->getPage();

    foreach ($highlightedTextRows as $indexRow => $expectedTexts) {
      foreach ($expectedTexts as $indexHighlighted => $expectedText) {
        $highlightedText = $page->find('xpath', "//tr[./td[text()=\"{$fieldName}\"]]/following-sibling::tr[{$indexRow}]/td[{$columnIndex}]/span[" . ($indexHighlighted + 1) . "]")
          ->getText();

        $this->assertEquals($expectedText, htmlspecialchars_decode($highlightedText, ENT_QUOTES | ENT_HTML401));
      }
    }
  }

  /**
   * Testing integration of "diff" module.
   */
  public function testDiffModule() {

    $this->drupalGet('node/7/edit');

    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    $teaserField = $page->find('xpath', '//*[@data-drupal-selector="edit-field-teaser-text-0-value"]');
    $initialTeaserText = $teaserField->getValue();
    $teaserText = 'Start with Text. ' . $initialTeaserText . ' End with Text.';
    $teaserField->setValue($teaserText);

    $this->clickButtonDrupalSelector($page, 'edit-field-teaser-media-current-items-0-remove-button');
    $this->selectMedia('field_teaser_media', 'image_browser', ['media:1']);

    $newParagraphText = 'One Ring to rule them all, One Ring to find them, One Ring to bring them all and in the darkness bind them!';
    $this->addTextParagraph('field_paragraphs', $newParagraphText);

    $this->addImageParagraph('field_paragraphs', ['media:5']);

    $this->clickArticleSave();

    $this->drupalGet('node/7/revisions');

    $lastLeftRadio = $page->find('xpath', '//table[contains(@class, "diff-revisions")]/tbody//tr[last()]//input[@name="radios_left"]');
    $lastLeftRadio->click();

    // Open diff page.
    $page->find('xpath', '//*[@data-drupal-selector="edit-submit"]')->click();

    // Validate that diff is correct.
    $this->validateDiff(
      'Teaser Text',
      ['1' => $initialTeaserText],
      [],
      ['1' => $teaserText],
      ['1' => ['Start with Text.', '. End with Text']]
    );

    $this->validateDiff(
      'Teaser Media',
      ['1' => 'DrupalCon Logo'],
      ['1' => ['DrupalCon Logo']],
      ['1' => 'Thunder'],
      ['1' => ['Thunder']]
    );

    $this->validateDiff(
      'Paragraphs > Text',
      ['1' => ''],
      [],
      ['1' => '<p>' . $newParagraphText . '</p>', '2' => ''],
      []
    );

    $this->validateDiff(
      'Paragraphs > Image',
      ['1' => ''],
      [],
      ['1' => 'Thunder City'],
      []
    );
  }

  /**
   * Testing integration of "access_unpublished" module.
   */
  public function testAccessUnpublished() {

    // Create article and save it as unpublished.
    $this->articleFillNew([
      'field_channel' => 1,
      'title[0][value]' => 'Article 1',
      'field_seo_title[0][value]' => 'Article 1',
    ]);
    $this->addTextParagraph('field_paragraphs', 'Article Text 1');
    $this->clickArticleSave();

    // Edit article and generate access unpubplished token.
    $this->drupalGet('node/10/edit');
    $this->expandAllTabs();
    $page = $this->getSession()->getPage();
    $this->scrollElementInView('[data-drupal-selector="edit-generate-token"]');
    $page->find('xpath', '//*[@data-drupal-selector="edit-generate-token"]')
      ->click();
    $this->waitUntilVisible('[data-drupal-selector="edit-token-table-1-link"]', 5000);
    $copyToClipboard = $page->find('xpath', '//*[@data-drupal-selector="edit-token-table-1-link"]');
    $tokenUrl = $copyToClipboard->getAttribute('data-clipboard-text');

    // Log-Out and check that URL with token works, but not URL without it.
    $loggedInUser = $this->loggedInUser;
    $this->drupalLogout();
    $this->drupalGet($tokenUrl);
    $this->assertSession()->pageTextContains('Article Text 1');
    $this->drupalGet('article-1');
    $noAccess = $this->xpath('//h1[contains(@class, "page-title")]//span[text() = "403"]');
    $this->assertEquals(1, count($noAccess));

    // Log-In and delete token -> check page can't be accessed.
    $this->drupalLogin($loggedInUser);
    $this->drupalGet('node/10/edit');
    $this->clickButtonDrupalSelector($page, 'edit-token-table-1-operation');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->clickArticleSave();

    // Log-Out and check that URL with token doesn't work anymore.
    $this->drupalLogout();
    $this->drupalGet($tokenUrl);
    $noAccess = $this->xpath('//h1[contains(@class, "page-title")]//span[text() = "403"]');
    $this->assertEquals(1, count($noAccess));

    // Log-In and publish article.
    $this->drupalLogin($loggedInUser);
    $this->drupalGet('node/10/edit');
    $this->clickArticleSave(2);

    // Log-Out and check that URL to article works.
    $this->drupalLogout();
    $this->drupalGet('article-1');
    $this->assertSession()->pageTextContains('Article Text 1');
  }

  /**
   * Testing integration of "metatag_facebook" module.
   */
  public function testFacebookMetaTags() {

    $facebookMetaTags = $this->generateMetaTagConfiguration([
      [
        'facebook fb:admins' => 'zuck',
        'facebook fb:pages' => 'some-fancy-fb-page-url',
        'facebook fb:app_id' => '1121151812167212,1121151812167213',
      ],
    ]);

    // Create Article with facebook meta tags and check it.
    $fieldValues = $this->generateMetaTagFieldValues($facebookMetaTags, 'field_meta_tags[0]');
    $fieldValues += [
      'field_channel' => 1,
      'title[0][value]' => 'Test FB MetaTags Article',
      'field_seo_title[0][value]' => 'Facebook MetaTags',
      'field_teaser_text[0][value]' => 'Facebook MetaTags Testing',
    ];
    $this->articleFillNew($fieldValues);
    $this->clickArticleSave();

    $this->checkMetaTags($facebookMetaTags);
  }

  /**
   * Select device for device preview.
   *
   * @param string $deviceId
   *   Identifier name for device.
   */
  protected function selectDevice($deviceId) {
    $page = $this->getSession()->getPage();

    $page->find('xpath', '//*[@id="responsive-preview-toolbar-tab"]/button')
      ->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->find('xpath', "//*[@id=\"responsive-preview-toolbar-tab\"]//button[@data-responsive-preview-name=\"{$deviceId}\"]")
      ->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Scroll to CSS selector element inside device preview.
   *
   * @param string $cssSelector
   *   CSS selector to scroll in view.
   */
  protected function scrollToInDevicePreview($cssSelector) {
    $this->getSession()->switchToIFrame('responsive-preview-frame');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->scrollElementInView($cssSelector);
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->switchToIFrame();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Change device rotation for device preview.
   */
  protected function changeDeviceRotation() {
    $this->getSession()->getPage()->find('xpath', '//*[@id="responsive-preview-orientation"]')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Testing integration of "device_preview" module.
   */
  public function testDevicePreview() {
    $windowSize = $this->getWindowSize();

    // Check channel page.
    $this->drupalGet('news');

    $topChannelCssSelector = 'a[href$="burda-launches-worldwide-coalition-industry-partners-and-releases-open-source-online-cms-platform"]';
    $midChannelCssSelector = 'a[href$="duis-autem-vel-eum-iriure"]';

    $windowSize['height'] = 950;
    $this->setWindowSize($windowSize);
    $this->selectDevice('iphone_7');
    $this->scrollToInDevicePreview($topChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch1')));
    $this->scrollToInDevicePreview($midChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch2')));

    $this->changeDeviceRotation();
    $this->scrollToInDevicePreview($topChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch3')));
    $this->scrollToInDevicePreview($midChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch4')));

    $windowSize['height'] = 1280;
    $this->setWindowSize($windowSize);
    $this->selectDevice('ipad_air_2');
    $this->scrollToInDevicePreview($topChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch5')));
    $this->scrollToInDevicePreview($midChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch6')));

    $this->changeDeviceRotation();
    $this->scrollToInDevicePreview($topChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch7')));
    $this->scrollToInDevicePreview($midChannelCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ch8')));

    $this->getSession()->getPage()->find('xpath', '//*[@id="responsive-preview-close"]')->click();

    // Testing of preview for single article.
    $topArticleCssSelector = '#block-thunder-base-content div.node__meta';
    $midArticleCssSelector = '#block-thunder-base-content div.field__items > div.field__item:nth-child(3)';
    $bottomArticleCssSelector = 'div.shariff';

    // Wait for CSS easing javascript.
    $waitTopImage = "jQuery('#block-thunder-base-content div.field__items > div.field__item:nth-child(1) img.b-loaded').css('opacity') === '1'";
    $waitMidGallery = "jQuery('#block-thunder-base-content div.field__items > div.field__item:nth-child(3) div.slick-active img.b-loaded').css('opacity') === '1'";

    $this->drupalGet('node/8/edit');

    $windowSize['height'] = 950;
    $this->setWindowSize($windowSize);
    $this->selectDevice('iphone_7');
    $this->scrollToInDevicePreview($topArticleCssSelector);
    $this->getSession()->wait(5000, $waitTopImage);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar1')));
    $this->scrollToInDevicePreview($midArticleCssSelector);
    $this->getSession()->wait(5000, $waitMidGallery);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar2')));
    $this->scrollToInDevicePreview($bottomArticleCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar3')));

    $this->changeDeviceRotation();
    $this->scrollToInDevicePreview($topArticleCssSelector);
    $this->getSession()->wait(5000, $waitTopImage);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar4')));
    $this->scrollToInDevicePreview($midArticleCssSelector);
    $this->getSession()->wait(5000, $waitMidGallery);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar5')));
    $this->scrollToInDevicePreview($bottomArticleCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar6')));

    $windowSize['height'] = 1280;
    $this->setWindowSize($windowSize);
    $this->selectDevice('ipad_air_2');
    $this->scrollToInDevicePreview($topArticleCssSelector);
    $this->getSession()->wait(5000, $waitTopImage);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar7')));
    $this->scrollToInDevicePreview($midArticleCssSelector);
    $this->getSession()->wait(5000, $waitMidGallery);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar8')));
    $this->scrollToInDevicePreview($bottomArticleCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar9')));

    $this->changeDeviceRotation();
    $this->scrollToInDevicePreview($topArticleCssSelector);
    $this->getSession()->wait(5000, $waitTopImage);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar10')));
    $this->scrollToInDevicePreview($midArticleCssSelector);
    $this->getSession()->wait(5000, $waitMidGallery);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar11')));
    $this->scrollToInDevicePreview($bottomArticleCssSelector);
    $this->assertTrue($this->compareScreenToImage($this->getScreenshotFile('test_device_preview_ar12')));
  }

  /**
   * Testing integration of "liveblog" module.
   */
  public function testLiveblog() {
    $pusherCredentials = json_decode(getenv('PUSHER_CREDENTIALS'), TRUE);
    if (empty($pusherCredentials)) {
      if ($this->isForkPullRequest()) {
        $this->markTestSkipped("Skip Live Blog test (missing secure environment variables)");

        return;
      }

      $this->fail("pusher credentials not provided.");
      return;
    }
    if (!\Drupal::service('module_installer')->install(['thunder_liveblog'])) {
      $this->fail("liveblog module couldn't be installed.");
      return;
    }

    // Configure Pusher.
    $this->logWithRole('administrator');

    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/config/content/liveblog');

    $fieldValues = [
      'plugin_settings[app_id]' => $pusherCredentials['app_id'],
      'plugin_settings[key]' => $pusherCredentials['key'],
      'plugin_settings[secret]' => $pusherCredentials['secret'],
      'plugin_settings[cluster]' => $pusherCredentials['cluster'],
      'channel_prefix' => getenv('TRAVIS_JOB_NUMBER') ? 'travis-' . getenv('TRAVIS_JOB_NUMBER') : 'liveblog-test',
    ];
    $this->setFieldValues($page, $fieldValues);
    $this->click('input[data-drupal-selector="edit-submit"]');

    $this->waitUntilVisible('.messages--status');

    $this->logWithRole(static::$defaultUserRole);

    // Add liveblog node.
    $fieldValues = [
      'title[0][value]' => 'Test Liveblog',
      'field_highlights[values][3]' => 'element',
      'field_posts_number_initial[0][value]' => '1',
    ];

    $this->drupalGet('node/add/liveblog');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->setFieldValues($this->getSession()->getPage(), $fieldValues);
    // 1 saves it as published in this case.
    $this->clickArticleSave(1);

    // Add first post.
    $page = $this->getSession()->getPage();

    $this->liveblogSetTitle($page, 'Normal post');
    $this->liveblogSetBody($page, "This is a normal text");
    $this->clickButtonDrupalSelector($page, "edit-submit");

    $this->waitUntilVisible('article[data-postid="1"]', 10000);

    // Add post with image.
    $this->liveblogSetTitle($page, 'Image post');

    $this->clickDropButton('field_embed_media_image_add_more', FALSE);

    $this->selectMedia("field_embed_media_0_subform_field_image", 'image_browser', ['media:1']);

    $this->liveblogSetBody($page, 'Very nice image post you have here!');

    $this->clickButtonDrupalSelector($page, "edit-submit");
    $this->createScreenshot($this->getScreenshotFolder() . '/ModuleIntegrationTest_Liveblog_ImagePost_' . date('Ymd_His') . '.png');

    $this->waitUntilVisible('article[data-postid="2"]', 10000);
    $this->waitUntilVisible('article[data-postid="2"] img.b-loaded', 10000);

    // Add post with twitter.
    $this->liveblogSetTitle($page, 'Twitter post');

    $this->createScreenshot($this->getScreenshotFolder() . '/ModuleIntegrationTest_Liveblog_TwitterPost_Add_' . date('Ymd_His') . '.png');
    $this->clickDropButton('field_embed_media_twitter_add_more');
    $this->waitUntilVisible('[name="field_embed_media[0][subform][field_media][0][inline_entity_form][field_url][0][uri]"]', 10000);
    $this->setFieldValue($page,
      'field_embed_media[0][subform][field_media][0][inline_entity_form][field_url][0][uri]',
      'https://twitter.com/tweetsauce/status/778001033142284288'
    );

    $this->liveblogSetBody($page, 'Very nice twitter post you have here!');

    $this->clickButtonDrupalSelector($page, "edit-submit");
    $this->createScreenshot($this->getScreenshotFolder() . '/ModuleIntegrationTest_Liveblog_TwitterPost_' . date('Ymd_His') . '.png');

    $this->waitUntilVisible('article[data-postid="3"]', 10000);
    $this->waitUntilVisible('[data-tweet-id="778001033142284288"]', 10000);

    // Add post with instagram.
    $this->liveblogSetTitle($page, 'Instagram post');

    $this->createScreenshot($this->getScreenshotFolder() . '/ModuleIntegrationTest_Liveblog_InstagramPost_Add_' . date('Ymd_His') . '.png');
    $this->clickDropButton('field_embed_media_instagram_add_more');
    $this->waitUntilVisible('[name="field_embed_media[0][subform][field_media][0][inline_entity_form][field_url][0][uri]"]', 10000);
    $this->setFieldValue($page,
      'field_embed_media[0][subform][field_media][0][inline_entity_form][field_url][0][uri]',
      'https://www.instagram.com/p/BNU5k6jhds9/'
    );

    $this->liveblogSetBody($page, 'Very nice instagram post you have here!');

    $this->clickButtonDrupalSelector($page, "edit-submit");
    $this->createScreenshot($this->getScreenshotFolder() . '/ModuleIntegrationTest_Liveblog_InstagramPost_' . date('Ymd_His') . '.png');

    $this->waitUntilVisible('article[data-postid="4"]', 10000);
    $this->waitUntilVisible('iframe[src^="https://www.instagram.com/p/BNU5k6jhds9/"]', 10000);

    // Check site with anonymous user.
    $url = $this->getUrl();
    $this->drupalLogout();

    $this->drupalGet($url);

    $this->waitUntilVisible('article[data-postid="4"]');
    $this->assertSession()->elementNotExists('css', 'article[data-postid="3"]');
    $this->assertSession()->elementNotExists('css', 'article[data-postid="2"]');
    $this->assertSession()->elementNotExists('css', 'article[data-postid="1"]');

    $this->scrollElementInView('article[data-postid="4"]');
    $this->waitUntilVisible('article[data-postid="3"]');
    $this->waitUntilVisible('article[data-postid="2"]');
    $this->waitUntilVisible('article[data-postid="1"]');

  }

  /**
   * Set the title of a liveblog post.
   *
   * @param \Behat\Mink\Element\DocumentElement $page
   *   Current active page.
   * @param string $title
   *   The title.
   */
  protected function liveblogSetTitle(DocumentElement $page, $title) {
    $this->setFieldValue($page, 'title[0][value]', $title);
  }

  /**
   * Set the body of a liveblog post.
   *
   * @param \Behat\Mink\Element\DocumentElement $page
   *   Current active page.
   * @param string $body
   *   The body.
   */
  protected function liveblogSetBody(DocumentElement $page, $body) {
    $this->fillCkEditor(
      $page,
      "textarea[name='body[0][value]']",
      $body
    );
  }

  /**
   * Testing integration of "thunder_riddle" module.
   */
  public function testRiddle() {
    $riddleToken = getenv('RIDDLE_TOKEN');

    if (empty($riddleToken)) {
      if ($this->isForkPullRequest()) {
        $this->markTestSkipped("Skip Riddle test (missing secure environment variables)");

        return;
      }

      $this->fail("Riddle token is not available.");

      return;
    }

    if (!\Drupal::service('module_installer')->install(['thunder_riddle'])) {
      $this->fail("Unable to install Thunder Riddle integration module.");

      return;
    }

    $this->logWithRole('administrator');

    // Adjust settings for Riddle.
    $this->drupalGet('admin/config/content/riddle_marketplace');
    $page = $this->getSession()->getPage();
    $this->setFieldValues($page, [
      'token' => $riddleToken,
    ]);
    $this->clickButtonDrupalSelector($page, 'edit-submit');

    // Log as editor user.
    $this->logWithRole(static::$defaultUserRole);

    // Fill article form with base fields.
    $this->articleFillNew([
      'field_channel' => 1,
      'title[0][value]' => 'Article 1',
      'field_seo_title[0][value]' => 'Article 1',
    ]);

    // Check loading of Riddles from riddle.com and creation of Riddle media.
    $paragraphIndex = $this->addParagraph('field_paragraphs', 'riddle');

    $buttonName = "field_paragraphs_{$paragraphIndex}_subform_field_riddle_entity_browser_entity_browser";
    $this->scrollElementInView("[name=\"{$buttonName}\"]");
    $page->pressButton($buttonName);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_riddle_browser');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Click button to load Riddles and compare thumbnails.
    $this->clickButtonDrupalSelector($page, 'edit-import-riddle');
    $this->assertTrue(
      $this->compareScreenToImage(
        $this->getScreenshotFile('test_riddle_eb_list'),
        ['width' => 600, 'height' => 380, 'x' => 60, 'y' => 115]
      )
    );

    // Close entity browser.
    $this->getSession()->switchToIFrame();
    $page->find('xpath', '//*[contains(@class, "ui-dialog-titlebar-close")]')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select first riddle.
    $this->selectMedia("field_paragraphs_{$paragraphIndex}_subform_field_riddle", 'riddle_browser', ['media:24']);

    // Select second riddle.
    $paragraphIndex = $this->addParagraph('field_paragraphs', 'riddle');
    $this->selectMedia("field_paragraphs_{$paragraphIndex}_subform_field_riddle", 'riddle_browser', ['media:25']);

    // Save article as unpublished.
    $this->clickArticleSave();

    // Assert that riddle iframes are correctly generated.
    $this->drupalGet('node/10');

    $this->assertSession()
      ->elementExists('xpath', '//div[contains(@class, "field--name-field-paragraphs")]/div[contains(@class, "field__item")][1]//iframe[contains(@src, "https://www.riddle.com/a/114982")]');
    $this->assertSession()
      ->elementExists('xpath', '//div[contains(@class, "field--name-field-paragraphs")]/div[contains(@class, "field__item")][2]//iframe[contains(@src, "https://www.riddle.com/a/114979")]');
  }

  /**
   * Testing integration of "AMP" module and theme.
   */
  public function testAmpIntegration() {
    if (!\Drupal::service('theme_installer')->install(['thunder_amp'])) {
      $this->fail("thunder_amp theme couldn't be installed.");
      return;
    }

    $this->drupalGet('/node/6', ['query' => ['amp' => 1]]);

    // Text paragraph.
    $this->assertSession()->pageTextContains('Board Member Philipp Welte explains');

    // Image paragraph.
    $this->assertSession()->elementExists('css', '.paragraph--type--image amp-img');
    $this->assertSession()->waitForElementVisible('css', '.paragraph--type--image amp-img img');

    $this->drupalGet('/node/7', ['query' => ['amp' => 1], 'fragment' => 'development=1']);

    // Gallery paragraph.
    $this->assertSession()->elementExists('css', '.paragraph--type--gallery amp-carousel');
    // Images in gallery paragraph.
    $this->assertSession()->waitForElementVisible('css', '.paragraph--type--gallery amp-carousel amp-img');
    $this->assertSession()->elementsCount('css', '.paragraph--type--gallery amp-carousel amp-img', 5);

    // Instagram Paragraph.
    $this->assertSession()->elementExists('css', '.paragraph--type--instagram amp-instagram[data-shortcode="2rh_YmDglx"]');
    $this->assertSession()->waitForElementVisible('css', '.paragraph--type--instagram amp-instagram[data-shortcode="2rh_YmDglx"] iframe');

    // Video Paragraph.
    $this->assertSession()->elementExists('css', '.paragraph--type--video amp-youtube[data-videoid="Ksp5JVFryEg"]');
    $this->assertSession()->waitForElementVisible('css', '.paragraph--type--video amp-youtube[data-videoid="Ksp5JVFryEg"] iframe');

    // Twitter Paragraph.
    $this->assertSession()->elementExists('css', '.paragraph--type--twitter amp-twitter[data-tweetid="731057647877787648"]');
    $this->assertSession()->waitForElementVisible('css', '.paragraph--type--twitter amp-twitter[data-tweetid="731057647877787648"] iframe');

    $this->getSession()->executeScript('AMPValidationSuccess = false; console.info = function(message) { if (message === "AMP validation successful.") { AMPValidationSuccess = true } }; amp.validator.validateUrlAndLog(document.location.href, document);');
    $this->assertJsCondition('AMPValidationSuccess === true', 10000, 'AMP validation successful.');

  }

  /**
   * Testing the content lock integration.
   */
  public function testContentLock() {

    $this->drupalGet('node/6/edit');
    $this->assertSession()->pageTextContains('This content is now locked against simultaneous editing. This content will remain locked if you navigate away from this page without saving or unlocking it.');

    $page = $this->getSession()->getPage();
    $page->find('xpath', '//*[@id="edit-unlock"]')->click();

    $page->find('xpath', '//*[@id="edit-submit"]')->click();
    $this->assertSession()->pageTextContains('Lock broken. Anyone can now edit this content.');

    $this->drupalGet('node/6/edit');
    $loggedInUser = $this->loggedInUser->label();

    $this->drupalLogout();

    // Login with other user.
    $this->logWithRole(static::$defaultUserRole);

    $this->drupalGet('node/6/edit');
    $this->assertSession()->pageTextContains('This content is being edited by the user ' . $loggedInUser . ' and is therefore locked to prevent other users changes.');
  }

}
