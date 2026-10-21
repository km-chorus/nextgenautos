<?php

declare(strict_types=1);

/**
 * Prepares the homepage hero slides (spec 10_spec_homepage_hero.md) from the static
 * configuration in app/config/homepage_hero.php. No database.
 *
 * Only files that really exist in the hero folder, are valid WebP images and have the exact
 * expected file names become slides, so a configuration mistake can never point the browser at
 * another file. With no usable image the result is an empty list and the page shows the plain
 * hero panel.
 */
final class HomepageHeroService
{
    public const MAX_SLIDES = 4;
    private const FOCUS = ['center', 'left', 'right', 'top', 'bottom'];
    private const TYPES = ['webp' => IMAGETYPE_WEBP, 'jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG];

    /** @param array{directory:string,slides:list<array<string,mixed>>} $config */
    public function __construct(private string $projectRoot, private array $config)
    {
    }

    public static function create(): self
    {
        /** @var array{directory:string,slides:list<array<string,mixed>>} $config */
        $config = require __DIR__ . '/../config/homepage_hero.php';

        return new self(dirname(__DIR__, 2), $config);
    }

    /**
     * Usable slides, rotated so $start is first (0 = natural order; null = random start).
     *
     * @return list<array{id:int,alt:string,focus:string,desktop:array{url:string,width:int,height:int},mobile:array{url:string,width:int,height:int},wide:?array{url:string,width:int,height:int}}>
     */
    public function slides(?int $start = null): array
    {
        $slides = [];
        foreach ($this->config['slides'] as $slide) {
            $prepared = $this->prepare($slide);
            if ($prepared !== null) {
                $slides[] = $prepared;
            }
            if (count($slides) === self::MAX_SLIDES) {
                break;
            }
        }
        $count = count($slides);
        if ($count < 2) {
            return $slides;
        }
        $offset = ($start ?? random_int(0, $count - 1)) % $count;

        return array_merge(array_slice($slides, $offset), array_slice($slides, 0, $offset));
    }

    /** @param array<string,mixed> $slide @return array<string,mixed>|null */
    private function prepare(array $slide): ?array
    {
        $id = (int) ($slide['id'] ?? 0);
        if ($id < 1 || $id > self::MAX_SLIDES) {
            return null;
        }
        $desktop = $this->image("hero-$id-desktop");
        if ($desktop === null) {
            return null;
        }
        $focus = (string) ($slide['focus'] ?? 'center');

        return [
            'id' => $id,
            'alt' => trim((string) ($slide['alt'] ?? '')),
            'focus' => in_array($focus, self::FOCUS, true) ? $focus : 'center',
            'desktop' => $desktop,
            'mobile' => $this->image("hero-$id-mobile") ?? $desktop,
            'wide' => $this->image("hero-$id-wide"),
        ];
    }

    /** @return array{url:string,width:int,height:int}|null */
    private function image(string $name): ?array
    {
        // $name is the base name ("hero-1-desktop"); the first existing file among .webp, .jpg and .png is used (WebP preferred)
        if (preg_match('/^hero-[1-4]-(?:desktop|mobile|wide)$/', $name) !== 1) {
            return null;
        }
        foreach (self::TYPES as $extension => $type) {
            $file = $this->projectRoot . '/' . $this->config['directory'] . '/' . $name . '.' . $extension;
            if (!is_file($file)) {
                continue;
            }
            $info = @getimagesize($file);
            if ($info === false || $info[2] !== $type || $info[0] < 1 || $info[1] < 1) {
                continue;   // the content must really be the type its extension says
            }
            $version = (string) (filemtime($file) ?: '');

            return ['url' => '/' . $this->config['directory'] . '/' . $name . '.' . $extension . ($version !== '' ? '?v=' . $version : ''), 'width' => $info[0], 'height' => $info[1]];
        }

        return null;
    }
}
