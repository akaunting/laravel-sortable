<?php

namespace Akaunting\Sortable\Support;

use Akaunting\Sortable\Exceptions\SortableException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class SortableLink
{
    /**
     * @throws SortableException
     */
    public static function render(array $parameters): string
    {
        list($sortColumn, $sortParameter, $title, $queryParameters, $anchorAttributes) = self::parseParameters($parameters);

        $title = self::applyFormatting($title, $sortColumn);

        if ($mergeTitleAs = config('sortable.inject_title_as', null)) {
            request()->merge([$mergeTitleAs => $title]);
        }

        list($icon, $direction) = self::determineDirection($sortColumn, $sortParameter);

        $trailingTag = self::formTrailingTag($icon);

        $anchorClass = self::getAnchorClass($sortParameter, $anchorAttributes);

        $anchorAttributesString = self::buildAnchorAttributesString($anchorAttributes);

        $queryString = self::buildQueryString($queryParameters, $sortParameter, $direction);

        $url = self::buildUrl($queryString, $anchorAttributes);

        return '<a' . $anchorClass . ' href="' . $url . '"' . $anchorAttributesString . '>' . e($title) . $trailingTag;
    }

    /**
     * @throws SortableException
     */
    public static function parseParameters(array $parameters): array
    {
        //TODO: let 2nd parameter be both title, or default query parameters
        //TODO: needs some checks before determining $title
        $explodeResult    = self::explodeSortParameter($parameters[0]);
        $sortColumn       = (empty($explodeResult)) ? $parameters[0] : $explodeResult[1];
        $title            = (count($parameters) === 1) ? null : $parameters[1];
        $queryParameters  = (isset($parameters[2]) && is_array($parameters[2])) ? $parameters[2] : [];
        $anchorAttributes = (isset($parameters[3]) && is_array($parameters[3])) ? $parameters[3] : [];

        return [$sortColumn, $parameters[0], $title, $queryParameters, $anchorAttributes];
    }

    /**
     * Explodes parameter if possible and returns array [column, relation]
     * Empty array is returned if explode could not run eg: separator was not found.
     *
     * @throws SortableException
     */
    public static function explodeSortParameter(string $parameter): array
    {
        $separator = config('sortable.relation_column_separator', '.');

        if (Str::contains($parameter, $separator)) {
            $oneToOneSort = explode($separator, $parameter);

            if (count($oneToOneSort) !== 2) {
                throw new SortableException();
            }

            return $oneToOneSort;
        }

        return [];
    }

    /**
     * @param string|Htmlable|null $title
     *
     * @return string|Htmlable
     */
    private static function applyFormatting($title, string $sortColumn)
    {
        if ($title instanceof Htmlable) {
            return $title;
        }

        if ($title === null) {
            $title = $sortColumn;
        } elseif ( ! config('sortable.format_custom_titles', true)){
            return $title;
        }

        $formatting_function = config('sortable.formatting_function', null);
        if (! is_null($formatting_function) && function_exists($formatting_function)) {
            $title = call_user_func($formatting_function, $title);
        }

        return $title;
    }

    private static function determineDirection($sortColumn, $sortParameter): array
    {
        $icon = self::selectIcon($sortColumn);

        if ((request()->get('sort') == $sortParameter) && in_array(request()->get('direction'), ['asc', 'desc'])) {
            $icon .= (request()->get('direction') === 'asc')
                            ? config('sortable.asc_suffix', '-asc')
                            : config('sortable.desc_suffix', '-desc');

            $direction = request()->get('direction') === 'desc' ? 'asc' : 'desc';

            return [$icon, $direction];
        } else {
            $icon      = config('sortable.icons.sortable');
            $direction = config('sortable.default_direction_unsorted', 'asc');

            return [$icon, $direction];
        }
    }

    private static function selectIcon($sortColumn): string
    {
        $icon = config('sortable.icons.default');

        foreach (config('sortable.types', []) as $value) {
            if (in_array($sortColumn, $value['fields'])) {
                $icon = $value['icon'];
            }
        }

        return $icon;
    }

    private static function formTrailingTag(string $icon): string
    {
        if (! config('sortable.icons.enabled', true)) {
            return '</a>';
        }

        $clickableIcon = config('sortable.icons.clickable', false);
        $trailingTag   = static::getIconHtml($icon) . '</a>';

        if ($clickableIcon === false) {
            $trailingTag = '</a>' . static::getIconHtml($icon);

            return $trailingTag;
        }

        return $trailingTag;
    }

    /**
     * Take care of special case, when `class` is passed to the sortablelink.
     */
    private static function getAnchorClass(string $sortColumn, array &$anchorAttributes = []): string
    {
        $class = [];

        $anchorClass = config('sortable.anchor_class', null);
        if ($anchorClass !== null) {
            $class[] = $anchorClass;
        }

        $activeClass = config('sortable.active_anchor_class', null);
        if ($activeClass !== null && self::shouldShowActive($sortColumn)) {
            $class[] = $activeClass;
        }

        $directionClassPrefix = config('sortable.direction_anchor_class_prefix', null);
        if ($directionClassPrefix !== null && self::shouldShowActive($sortColumn)) {
            $class[] = $directionClassPrefix . (request()->get('direction') === 'asc')
                                                    ? config('sortable.asc_suffix', '-asc')
                                                    : config('sortable.desc_suffix', '-desc');
        }

        if (isset($anchorAttributes['class'])) {
            $class = array_merge($class, explode(' ', $anchorAttributes['class']));

            unset($anchorAttributes['class']);
        }

        return (empty($class)) ? '' : ' class="' . implode(' ', $class) . '"';
    }

    private static function shouldShowActive(string $sortColumn): bool
    {
        return request()->has('sort') && (request()->get('sort') == $sortColumn);
    }

    private static function buildQueryString(array $queryParameters, string $sortParameter, string $direction): string
    {
        $checkStrlenOrArray = function ($element) {
            return is_array($element) ? $element : strlen($element);
        };

        $persistParameters = array_filter(request()->except('sort', 'direction', 'page'), $checkStrlenOrArray);
        $queryString       = http_build_query(array_merge($queryParameters, $persistParameters, [
            'sort'      => $sortParameter,
            'direction' => $direction,
        ]));

        return $queryString;
    }

    private static function buildAnchorAttributesString(array $anchorAttributes): string
    {
        if (empty($anchorAttributes)) {
            return '';
        }

        unset($anchorAttributes['href']);

        $attributes = [];
        foreach ($anchorAttributes as $k => $v) {
            $attributes[] = $k . ('' != $v ? '="' . $v . '"' : '');
        }

        return ' ' . implode(' ', $attributes);
    }

    private static function buildUrl(string $queryString, array $anchorAttributes): string
    {
        $path = isset($anchorAttributes['href']) ? $anchorAttributes['href'] : request()->path();

        return url($path . "?" . $queryString);
    }

    public static function getIconHtml($icon = null): string
    {
        $prefix = config('sortable.icons.prefix');
        $suffix = config('sortable.icons.suffix');
        $icon = $icon ?: config('sortable.icons.default');
        $wrapper = config('sortable.icons.wrapper');

        return $prefix . str_replace('{icon}', $icon, $wrapper) . $suffix;
    }
}
