<?php

namespace KodiCMS\Navigation;

use Countable;
use Gate;
use Iterator;
use KodiCMS\Navigation\Contracts\NavigationSectionInterface;

class Section extends ItemDecorator implements Countable, Iterator, NavigationSectionInterface
{
    const ROOT_NAME = 'root';

    /**
     * @var Page[]
     */
    protected $pages = [];

    /**
     * @var Section[]
     */
    protected $sections = [];

    /**
     * @var int
     */
    protected $currentKey = 0;

    /**
     * @var Navigation
     */
    protected $navigation;

    /**
     * @param Navigation $navigation
     * @param array      $data
     *
     * @return static
     */
    public static function factory(Navigation $navigation, array $data = [])
    {
        return new static($navigation, $data);
    }

    /**
     * Section constructor.
     *
     * @param Navigation $navigation
     * @param array      $data
     */
    public function __construct(Navigation $navigation, array $data = [])
    {
        $this->setAttribute($data);
        $this->navigation = $navigation;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->getAttribute('id');
    }

    /**
     * @return Page[]
     */
    public function getPages()
    {
        return $this->pages;
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->getName() == static::ROOT_NAME;
    }

    /**
     * @return Section[]
     */
    public function getSections()
    {
        return $this->sections;
    }

    /**
     * @param array $pages
     *
     * @return $this
     */
    public function addPages(array $pages)
    {
        foreach ($pages as $page) {
            if (isset($page['children'])) {
                $section = $this->navigation->findSectionOrCreate($page['name'], $this);

                if (isset($page['icon'])) {
                    $section->setIcon($page['icon']);
                }

                if (count($page['children']) > 0) {
                    $section->addPages($page['children']);
                }
            } else {
                $this->addPage(new Page($page));
            }
        }

        return $this;
    }

    /**
     * @param NavigationSectionInterface $section
     * @param int                        $priority
     *
     * @return Section
     */
    public function addSection(NavigationSectionInterface $section, $priority = 1)
    {
        intval($priority);
        while (isset($this->sections[$priority])) {
            $priority++;
        }

        $this->sections[$priority] = $section;
        $section->setRootSection($this);

        return $section;
    }

    /**
     * @param NavigationPageInterface $page
     * @param int                     $priority
     *
     * @return $this
     */
    public function addPage(NavigationPageInterface &$page, $priority = 1)
    {
        intval($priority);

        $permissions = $page->getPermissions();
        if (!empty($permissions) and Gate::denies('navigation-page-view', $page)) {
            return $this;
        }

        if (isset($page->priority)) {
            $priority = (int) $page->priority;
        }

        if ($page instanceof static) {
            $this->addSection($page);
        } else {
            while (isset($this->pages[$priority])) {
                $priority++;
            }

            $this->pages[$priority] = $page;
            $page->setRootSection($this);
        }

        return $this->update()->sort();
    }

    /**
     * @param string $currentUri
     *
     * @return bool
     */
    public function findActivePageByUri($currentUri)
    {
        $found = false;
        $adminDirName = url('admin');

        foreach ($this->getPages() as $page) {
            $url = $page->getUrl();
            $len = strpos($url, $adminDirName);
            if ($len !== false) {
                $len += strlen($adminDirName);
            }

            $url = substr($url, $len);

            $len = strpos($currentUri, $adminDirName);
            if ($len !== false) {
                $len += strlen($adminDirName);
            }

            $uri = substr($currentUri, $len);
            $pos = strpos($uri, $url);
            if (!empty($url) and $pos !== false and $pos < 5) {
                $page->setStatus(true);

                $this->navigation->setCurrentPage($page);

                $found = true;
                break;
            }
        }

        if ($found === false) {
            foreach ($this->getSections() as $section) {
                $found = $section->findActivePageByUri($currentUri);
                if ($found !== false) {
                    return $found;
                }
            }
        }

        return $found;
    }

    /**
     * @param string $name
     *
     * @return Section
     */
    public function findSection($name)
    {
        foreach ($this->getSections() as $section) {
            if ($section->getKey() == $name) {
                return $section;
            }
        }

        foreach ($this->getSections() as $section) {
            $found = $section->findSection($name);
            if (!is_null($found)) {
                return $found;
            }
        }

        return;
    }

    /**
     * @param string $uri
     *
     * @return null|Page
     */
    public function &findPageByUri($uri)
    {
        foreach ($this->getPages() as $page) {
            if ($page->getUrl() == $uri) {
                return $page;
            }
        }

        foreach ($this->getSections() as $section) {
            $found = $section->findPageByUri($uri);
            if (!is_null($found)) {
                return $found;
            }
        }

        return;
    }

    /**
     * @return Section
     */
    public function update()
    {
        return $this;
    }

    /**
     * @return Section
     */
    public function sort()
    {
        uasort($this->sections, function (Section $a, Section $b) {
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }

            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        ksort($this->pages);

        return $this;
    }

    /**
     * Implements [Countable::count], returns the total number of rows.
     *
     *     echo count($result);
     *
     * @return int
     */
    public function count()
    {
        return count($this->pages);
    }

    /**
     * Implements [Iterator::key], returns the current row number.
     *
     *     echo key($result);
     *
     * @return int
     */
    public function key()
    {
        return key($this->pages);
    }

    /**
     * Implements [Iterator::key], returns the current breadcrumb item.
     *
     *     echo key($result);
     *
     * @return int
     */
    public function current()
    {
        return current($this->pages);
    }

    /**
     * Implements [Iterator::next], moves to the next row.
     *
     *     next($result);
     *
     * @return $this
     */
    public function next()
    {
        next($this->pages);
    }

    /**
     * Implements [Iterator::prev], moves to the previous row.
     *
     *     prev($result);
     *
     * @return $this
     */
    public function prev()
    {
        --$this->currentKey;
    }

    /**
     * Implements [Iterator::rewind], sets the current row to zero.
     *
     *     rewind($result);
     *
     * @return $this
     */
    public function rewind()
    {
        reset($this->pages);
    }

    /**
     * Implements [Iterator::valid], checks if the current row exists.
     *
     * [!!] This method is only used internally.
     *
     * @return bool
     */
    public function valid()
    {
        $key = key($this->pages);

        return ($key !== null and $key !== false);
    }
}