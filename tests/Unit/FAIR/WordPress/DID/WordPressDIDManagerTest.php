<?php

/**
 * WordPressDIDManager tests.
 *
 * @package Tests\Unit\FAIR\WordPress\DID
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\WordPress\DID;

use FAIR\DID\DIDManager;
use FAIR\WordPress\DID\WordPressDIDManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for WordPressDIDManager.
 */
class WordPressDIDManagerTest extends TestCase
{
    /**
     * Temporary directory path.
     *
     * @var string
     */
    private string $temp_dir;

    /**
     * Core DID manager mock.
     *
     * @var DIDManager&MockObject
     */
    private DIDManager $did_manager;

    protected function setUp(): void
    {
        $this->temp_dir = sys_get_temp_dir() . '/did-manager-wordpress-' . uniqid();
        mkdir($this->temp_dir, 0o755, true);
        $this->did_manager = $this->createMock(DIDManager::class);
    }

    protected function tearDown(): void
    {
        $this->delete_directory($this->temp_dir);
    }

    public function testDetectPackageTypeReturnsPlugin(): void
    {
        $plugin_dir = $this->temp_dir . '/plugin';
        mkdir($plugin_dir, 0o755, true);
        file_put_contents(
            $plugin_dir . '/plugin.php',
            "<?php\n/**\n * Plugin Name: Test Plugin\n */\n",
        );

        $manager = new WordPressDIDManager($this->did_manager);

        $this->assertSame('plugin', $manager->detect_package_type($plugin_dir));
    }

    public function testDetectPackageTypeReturnsTheme(): void
    {
        $theme_dir = $this->temp_dir . '/theme';
        mkdir($theme_dir, 0o755, true);
        file_put_contents(
            $theme_dir . '/style.css',
            "/*\nTheme Name: Test Theme\n*/\n",
        );

        $manager = new WordPressDIDManager($this->did_manager);

        $this->assertSame('theme', $manager->detect_package_type($theme_dir));
    }

    public function testInjectPackageIdUpdatesPluginHeader(): void
    {
        $plugin_dir = $this->temp_dir . '/plugin';
        mkdir($plugin_dir, 0o755, true);
        $plugin_file = $plugin_dir . '/plugin.php';
        file_put_contents(
            $plugin_file,
            "<?php\n/**\n * Plugin Name: Test Plugin\n * Version: 1.0.0\n */\n",
        );

        $manager = new WordPressDIDManager($this->did_manager);
        $manager->inject_package_id($plugin_dir, 'did:plc:test123');

        $content = file_get_contents($plugin_file);

        $this->assertIsString($content);
        $this->assertStringContainsString('Plugin ID: did:plc:test123', $content);
    }

    public function testCreatePackageDidPassesGenericMetadataToCoreManager(): void
    {
        $plugin_dir = $this->temp_dir . '/plugin';
        mkdir($plugin_dir, 0o755, true);
        file_put_contents(
            $plugin_dir . '/plugin.php',
            "<?php\n/**\n * Plugin Name: Test Plugin\n */\n",
        );

        $this->did_manager
            ->expects($this->once())
            ->method('create_did')
            ->with(
                'example-handle',
                'https://example.com/service',
                'plugin',
                ['packagePath' => $plugin_dir],
            )
            ->willReturn(['did' => 'did:plc:test123']);

        $manager = new WordPressDIDManager($this->did_manager);
        $result = $manager->create_package_did(
            $plugin_dir,
            'example-handle',
            'https://example.com/service',
            false,
        );

        $this->assertSame('did:plc:test123', $result['did']);
        $this->assertSame('plugin', $result['type']);
    }

    private function delete_directory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $path . '/' . $item;
            if (is_dir($full_path)) {
                $this->delete_directory($full_path);
                continue;
            }

            unlink($full_path);
        }

        rmdir($path);
    }
}
