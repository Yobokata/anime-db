<?php

/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AnimeDbBundle\Tests\Event\Listener;

use AnimeDb\Bundle\AnimeDbBundle\Event\Listener\UpdateItself;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Test UpdateItself
 *
 * @package AnimeDb\Bundle\AnimeDbBundle\Tests\Event\Listener
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class UpdateItselfTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Listener
     *
     * @var \AnimeDb\Bundle\AnimeDbBundle\Event\Listener\UpdateItself
     */
    protected $listener;

    /**
     * Root dir
     *
     * @var string
     */
    protected $root_dir;

    /**
     * Event dir
     *
     * @var string
     */
    protected $event_dir;

    /**
     * Event
     *
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $event;

    /**
     * Filesystem
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->fs = new Filesystem();
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    public function setUp()
    {
        parent::setUp();
        // real path /foo/
        $this->root_dir = sys_get_temp_dir().'/tests/foo/bar/';
        $this->event_dir = sys_get_temp_dir().'/tests/baz/';
        $this->fs->mkdir([$this->root_dir, $this->event_dir]);

        $this->listener = new UpdateItself($this->root_dir);

        $this->event = $this->getMockBuilder('\AnimeDb\Bundle\AnimeDbBundle\Event\UpdateItself\Downloaded')
            ->disableOriginalConstructor()
            ->getMock();
        $this->event
            ->expects($this->any())
            ->method('getPath')
            ->will($this->returnValue($this->event_dir));
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
        $this->fs->remove([$this->root_dir, $this->event_dir]);
    }

    /**
     * Test merge composer requirements
     */
    public function testOnAppDownloadedMergeComposerRequirements()
    {
        $composer = json_encode([
            'name' => 'foo',
            'require' => [
                'bar',
                'baz'
            ],
        ]);
        file_put_contents($this->root_dir.'/../composer.json', $composer);
        file_put_contents($this->event_dir.'/composer.json', $composer);

        $this->listener->onAppDownloadedMergeComposerRequirements($this->event); // test

        $this->assertFileEquals($this->root_dir.'/../composer.json', $this->event_dir.'/composer.json');
    }

    /**
     * Test do merge composer requirements
     */
    public function testOnAppDownloadedMergeComposerRequirementsMerge()
    {
        $old_composer = [
            'name' => 'foo',
            'require' => [
                'bar'
            ],
        ];
        $new_composer = [
            'name' => 'foo',
            'require' => [
                'baz'
            ],
        ];
        file_put_contents($this->root_dir.'/../composer.json', json_encode($old_composer));
        file_put_contents($this->event_dir.'/composer.json', json_encode($new_composer));

        $this->listener->onAppDownloadedMergeComposerRequirements($this->event); // test

        $expected = $old_composer;
        $expected['require'] = array_merge($expected['require'], $new_composer['require']);
        $this->assertEquals(
            json_encode($expected, JSON_PRETTY_PRINT),
            file_get_contents($this->event_dir.'/composer.json')
        );
    }

    /**
     * Test merge configs
     */
    public function testOnAppDownloadedMergeConfigs()
    {
        $files = [
            'app/config/parameters.yml',
            'app/config/vendor_config.yml',
            'app/config/routing.yml',
            'app/bundles.php'
        ];
        $this->fs->mkdir([$this->event_dir.'/app/config/', $this->event_dir.'/app/']);
        $this->initFiles($files, $this->root_dir.'../');

        $this->listener->onAppDownloadedMergeConfigs($this->event); // test

        foreach ($files as $file) {
            $this->assertFileEquals($this->root_dir.'../'.$file, $this->event_dir.$file);
        }
    }

    /**
     * Init files
     *
     * @param array $files
     * @param string $dir
     */
    protected function initFiles(array $files, $dir)
    {
        foreach ($files as $file) {
            if (!is_dir($path = dirname($dir.$file))) {
                $this->fs->mkdir($path);
            }
            file_put_contents($dir.$file, $file);
        }
    }
}