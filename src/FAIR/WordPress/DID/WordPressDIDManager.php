<?php

/**
 * WordPress-specific adapter around the core DID manager.
 *
 * @package FAIR\WordPress\DID
 */

declare(strict_types=1);

namespace FAIR\WordPress\DID;

use FAIR\DID\DIDManager;
use FAIR\WordPress\DID\Parsers\MetadataGenerator;
use FAIR\WordPress\DID\Parsers\PluginHeaderParser;

/**
 * Coordinates WordPress package workflows using the core DID manager.
 */
class WordPressDIDManager
{
    /**
     * Maximum bytes to inspect when detecting package headers.
     */
    private const int MAX_HEADER_SIZE = 8192;

    /**
     * Core DID manager.
     *
     * @var DIDManager
     */
    private DIDManager $did_manager;

    /**
     * Plugin header parser.
     *
     * @var PluginHeaderParser
     */
    private PluginHeaderParser $plugin_header_parser;

    /**
     * Constructor.
     *
     * @param DIDManager               $did_manager Core DID manager.
     * @param PluginHeaderParser|null  $plugin_header_parser Optional parser override.
     */
    public function __construct(DIDManager $did_manager, ?PluginHeaderParser $plugin_header_parser = null)
    {
        $this->did_manager = $did_manager;
        $this->plugin_header_parser = $plugin_header_parser ?? new PluginHeaderParser();
    }

    /**
     * Create a DID for a WordPress package.
     *
     * @param string      $package_path Path to the plugin or theme.
     * @param string|null $handle Optional handle.
     * @param string|null $service_endpoint Optional service endpoint.
     * @param bool        $inject_id Whether to write the DID back into the package header.
     * @return array Created DID info.
     */
    public function create_package_did(
        string $package_path,
        ?string $handle = null,
        ?string $service_endpoint = null,
        bool $inject_id = false,
    ): array {
        $type = $this->detect_package_type($package_path);
        $result = $this->did_manager->create_did(
            handle: $handle,
            service_endpoint: $service_endpoint,
            type: $type,
            metadata: ['packagePath' => $package_path],
        );

        if ($inject_id) {
            $this->inject_package_id($package_path, $result['did']);
        }

        $result['type'] = $type;

        return $result;
    }

    /**
     * Generate FAIR metadata for a WordPress package.
     *
     * @param string      $package_path Path to the plugin or theme.
     * @param string|null $slug Optional slug override.
     * @param string|null $did Optional DID to include.
     * @return array Generated metadata.
     */
    public function generate_metadata(string $package_path, ?string $slug = null, ?string $did = null): array
    {
        $generator = MetadataGenerator::from_path($package_path);
        $type = $this->detect_package_type($package_path);

        if (null !== $type) {
            $generator->set_type($type);
        }

        if (null !== $slug) {
            $generator->set_slug($slug);
        }

        if (null !== $did) {
            $generator->set_did($did);
        }

        return $generator->generate();
    }

    /**
     * Write FAIR metadata to disk.
     *
     * @param string      $package_path Path to the plugin or theme.
     * @param string|null $output_path Optional output path.
     * @param string|null $slug Optional slug override.
     * @param string|null $did Optional DID to include.
     * @return string Path written to.
     */
    public function write_metadata(
        string $package_path,
        ?string $output_path = null,
        ?string $slug = null,
        ?string $did = null,
    ): string {
        $generator = MetadataGenerator::from_path($package_path);
        $type = $this->detect_package_type($package_path);

        if (null !== $type) {
            $generator->set_type($type);
        }

        if (null !== $slug) {
            $generator->set_slug($slug);
        }

        if (null !== $did) {
            $generator->set_did($did);
        }

        $target_path = $output_path ?? rtrim($package_path, '/\\') . '/metadata.json';
        $generator->write_to_file($target_path);

        return $target_path;
    }

    /**
     * Detect whether a path contains a plugin or theme.
     *
     * @param string $path Package path.
     * @return string|null Detected type.
     */
    public function detect_package_type(string $path): ?string
    {
        $theme_file = rtrim($path, '/\\') . '/style.css';
        if (file_exists($theme_file)) {
            $content = file_get_contents($theme_file, false, null, 0, self::MAX_HEADER_SIZE);
            if (is_string($content) && preg_match('/Theme Name:/i', $content)) {
                return 'theme';
            }
        }

        $main_file = $this->plugin_header_parser->find_main_file($path);
        if (null !== $main_file) {
            return 'plugin';
        }

        return null;
    }

    /**
     * Inject a DID into a package header.
     *
     * @param string $path Package path.
     * @param string $did DID to inject.
     */
    public function inject_package_id(string $path, string $did): void
    {
        $type = $this->detect_package_type($path);
        if ('theme' === $type) {
            $this->inject_theme_id($path, $did);
            return;
        }

        if ('plugin' === $type) {
            $this->inject_plugin_id($path, $did);
            return;
        }

        throw new \RuntimeException("Unable to determine WordPress package type for: {$path}");
    }

    /**
     * Inject a DID into the main plugin file.
     *
     * @param string $path Plugin path.
     * @param string $did DID to inject.
     */
    private function inject_plugin_id(string $path, string $did): void
    {
        $main_file = $this->plugin_header_parser->find_main_file($path);
        if (null === $main_file) {
            throw new \RuntimeException("Could not find main plugin file in: {$path}");
        }

        $content = file_get_contents($main_file);
        if (!is_string($content)) {
            throw new \RuntimeException("Failed to read plugin file: {$main_file}");
        }

        $updated = $this->upsert_header_field($content, 'Plugin Name', 'Plugin ID', $did);

        if (false === file_put_contents($main_file, $updated)) {
            throw new \RuntimeException("Failed to write to: {$main_file}");
        }
    }

    /**
     * Inject a DID into a theme stylesheet header.
     *
     * @param string $path Theme path.
     * @param string $did DID to inject.
     */
    private function inject_theme_id(string $path, string $did): void
    {
        $style_file = rtrim($path, '/\\') . '/style.css';
        if (!file_exists($style_file)) {
            throw new \RuntimeException("Could not find theme stylesheet in: {$path}");
        }

        $content = file_get_contents($style_file);
        if (!is_string($content)) {
            throw new \RuntimeException("Failed to read theme stylesheet: {$style_file}");
        }

        $updated = $this->upsert_header_field($content, 'Theme Name', 'Theme ID', $did);

        if (false === file_put_contents($style_file, $updated)) {
            throw new \RuntimeException("Failed to write to: {$style_file}");
        }
    }

    /**
     * Insert or update a header field inside a WordPress comment block.
     *
     * @param string $content File contents.
     * @param string $anchor_field Existing header field used as an insertion anchor.
     * @param string $target_field Header field to upsert.
     * @param string $value Value to write.
     * @return string Updated content.
     */
    private function upsert_header_field(
        string $content,
        string $anchor_field,
        string $target_field,
        string $value,
    ): string {
        $field_pattern = '/(\*?\s*' . preg_quote($target_field, '/') . ':\s*).*/i';
        if (preg_match($field_pattern, $content)) {
            return (string) preg_replace($field_pattern, '$1' . $value, $content, 1);
        }

        $anchor_pattern = '/(\*?\s*' . preg_quote($anchor_field, '/') . ':\s*[^\n]+)/i';
        $replacement = "$1\n * {$target_field}: {$value}";

        $updated = preg_replace($anchor_pattern, $replacement, $content, 1);
        if (null === $updated || $updated === $content) {
            throw new \RuntimeException("Could not inject {$target_field} into package header.");
        }

        return $updated;
    }
}
