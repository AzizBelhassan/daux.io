<?php namespace Todaymade\Daux\Format\HTML;

use Todaymade\Daux\Tree\Root;

class ContentPage extends \Todaymade\Daux\Format\Base\ContentPage
{
    public Template $templateRenderer;

    private string $language;

    private bool $homepage;

    private function isHomepage(): bool
    {
        // If the current page isn't the index, no chance it is the landing page
        if ($this->file->getParent()->getIndexPage() != $this->file) {
            return false;
        }

        // If the direct parent is root, this is the homepage
        return $this->file->getParent() instanceof Root;
    }

    private function isLanding(): bool
    {
        return $this->config->getHTML()->hasLandingPage() && $this->homepage;
    }

    private function initialize()
    {
        $this->homepage = $this->isHomepage();

        $this->language = '';
        if ($this->config->isMultilanguage() && count($this->file->getParents())) {
            $language_dir = $this->file->getParents()[0];
            $this->language = $language_dir->getName();
        }
    }

    /**
     * @param \Todaymade\Daux\Tree\Directory[] $parents
     * @param bool $multilanguage
     *
     * @return array
     */
    private function getBreadcrumbTrail($parents, $multilanguage)
    {
        if ($multilanguage && !empty($parents)) {
            $parents = array_splice($parents, 1);
        }
        $breadcrumb_trail = [];
        if (!empty($parents)) {
            foreach ($parents as $node) {
                $page = $node->getIndexPage() ?: $node->getFirstPage();
                $breadcrumb_trail[] = ['title' => $node->getTitle(), 'url' => $page ? $page->getUrl() : ''];
            }
        }

        return $breadcrumb_trail;
    }

    protected function generatePage()
    {
        $this->initialize();
        $config = $this->config;

        $entry_page = [];
        if ($this->homepage) {
            if ($config->isMultilanguage()) {
                foreach ($config->getLanguages() as $key => $name) {
                    $entry_page[$name] = $config->getBasePage() . $config->getEntryPage()[$key]->getUrl();
                }
            } else {
                $entry_page['__VIEW_DOCUMENTATION__'] = $config->getBasePage() . $config->getEntryPage()->getUrl();
            }
        }

        $page = [
            'entry_page' => $entry_page,
            'homepage' => $this->homepage,
            'title' => $this->file->getTitle(),
            'filename' => $this->file->getName(),
            'language' => $this->language,
            'path' => $this->file->getPath(),
            'relative_path' => $this->file->getRelativePath(),
            'modified_time' => filemtime($this->file->getPath()),
            'markdown' => $this->content,
            'request' => $config->getRequest(),
            'content' => $this->getPureContent(),
            'breadcrumbs' => $config->getHTML()->hasBreadcrumbs(),
            'prev' => $this->file->getPrevious(),
            'next' => $this->file->getNext(),
            'attributes' => $this->file->getAttribute(),
        ];

        if ($page['breadcrumbs']) {
            $page['breadcrumb_trail'] = $this->getBreadcrumbTrail($this->file->getParents(), $config->isMultilanguage());
            $page['breadcrumb_separator'] = $this->config->getHTML()->getBreadcrumbsSeparator();

            if ($this->homepage) {
                $page['breadcrumb_trail'] = [['title' => $this->file->getTitle(), 'url' => '']];
            }
        }

        $context = ['page' => $page, 'config' => $config];

        $template = 'theme::content';
        if ($this->isLanding()) {
            $template = 'theme::home';
        }

        if (array_key_exists('template', $page['attributes'])) {
            $template = 'theme::' . $page['attributes']['template'];
        }

        return $this->templateRenderer->render($template, $context);
    }
}
