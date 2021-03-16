<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use DOMDocument;
use DOMNode;
use DOMXPath;
use TYPO3\CMS\Redirects\Repository\Demand;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\CMS\Redirects\Repository\RedirectRepository;

/**
 * Class InputSlugElement
 *
 * @package Wazum\Sluggi\Backend\Form
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class InputSlugElement extends \TYPO3\CMS\Backend\Form\Element\InputSlugElement
{
    /**
     * Additional group/admin check for slug edit button
     */
    public function render(): array
    {
        $result = parent::render();

        if ($this->data['tableName'] !== 'pages') {
            return $result;
        }

        $context = GeneralUtility::makeInstance(Context::class);
        if( $context->getPropertyFromAspect('workspace', 'isOffline') ) {
            if ($this->data['databaseRow']['t3ver_state'] !== VersionState::NEW_PLACEHOLDER_VERSION) {
                $result['html'] = "<p>Cette page a déjà été publiée, il n'est donc pas possible de modifier le segment d'URL
                    en WEBDEV (Espace de travail de révision)</p><p> URL publié: " . $this->data['databaseRow']['slug'] ."</p>";
                return $result;
            }

            $demand = new Demand(1, '', $this->data['databaseRow']['slug']);
            $redirectRepository = GeneralUtility::makeInstance(RedirectRepository::class, $demand);
            $redirects = $redirectRepository->findRedirectsByDemand();
            if( count($redirects) > 0 ) {
                $result['html'] = "<p>Attention, des redirections existes qui créeront des conflits avec cette page.
                    Veuillez faire une demande pour supprimer ces redirections OU changer  le segment d'URL avant de publier la page.</p>" . $result['html'];
                return $result;
            }
        } else {
            $demand = new Demand(1, '', $this->data['databaseRow']['slug']);
            $redirectRepository = GeneralUtility::makeInstance(RedirectRepository::class, $demand);
            $redirects = $redirectRepository->findRedirectsByDemand();
            if( count($redirects) > 0 ) {
              $result['html'] = "<p>Attention, des redirections existes qui créeront des conflits avec cette page.
                      Veuillez faire une demande pour supprimer ces redirections.</p>" . $result['html'];
              return $result;
            }
            if( ! $GLOBALS['BE_USER']->isAdmin()) {
               $result['html'] = "<p>Attention, modifier le segment d'URL créera automatiquement des redirections et ajustera le segment d'URL des sous-pages. Avant de changer ce champ, veuillez lire la <a style='text-decoration: underline;'>documentation</a>.</p>" . $result['html'];
            }
        }

        $languageId = $this->getLanguageId($this->data['tableName'], $this->data['databaseRow']);
        $page = $this->data['databaseRow'];

        if (!PermissionHelper::hasFullPermission()) {
            // Remove edit and recalculate buttons if slug segment is locked
            if (PermissionHelper::isLocked($page)) {
                $result['html'] .= '<script>var tx_sluggi_lock = true;</script>';
            } else {
                $synchronize = (bool)Configuration::get('synchronize');
                if (isset($page['tx_sluggi_sync']) && false === (bool)$page['tx_sluggi_sync']) {
                    $synchronize = false;
                }
                if ($synchronize) {
                    $result['html'] .= '<script>var tx_sluggi_sync = true;</script>';
                }
            }

            $mountRootPage = PermissionHelper::getTopmostAccessiblePage((int)$this->data['databaseRow']['uid']);
            $inaccessibleSlugSegments = SluggiSlugHelper::getSlug((int)$mountRootPage['pid'], $languageId);
            $prefix = ($this->data['customData'][$this->data['fieldName']]['slugPrefix'] ?? '') . $inaccessibleSlugSegments;
            $editableSlugSegments = $this->data['databaseRow']['slug'];
            $allowOnlyLastSegment = (bool)Configuration::get('last_segment_only');
            if (!empty($inaccessibleSlugSegments) && strpos($editableSlugSegments, $inaccessibleSlugSegments) === 0) {
                $editableSlugSegments = substr($editableSlugSegments, strlen($inaccessibleSlugSegments));
            }
            if ($allowOnlyLastSegment && !empty($editableSlugSegments)) {
                $segments = explode('/', $editableSlugSegments);
                $editableSlugSegments = '/' . array_pop($segments);
                $prefix .= implode('/', $segments);
            }

            $result['html'] = $this->replaceValues($result['html'], $prefix, $editableSlugSegments);
        }

        $result['requireJsModules'][0] = [
            'TYPO3/CMS/Sluggi/SlugElement' =>
                $result['requireJsModules'][0]['TYPO3/CMS/Backend/FormEngine/Element/SlugElement']
        ];

        return $result;
    }

    protected function getLanguageId(string $table, array $row): int
    {
        $languageId = 0;

        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField']) &&
            !empty($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
            $languageId = (int)((is_array($row[$languageField]) ?
                    $row[$languageField][0] :
                    $row[$languageField]) ?? 0
            );
        }

        return $languageId;
    }

    protected function replaceValues(string $html, string $prefix, string $segments): string
    {
        return preg_replace(
            [
                '/(<input.*?class=".*?t3js-form-field-slug-input.*?".*?)placeholder="(.*?)"(.*?>)/ius',
                '/(<input.*?class=".*?t3js-form-field-slug-readonly.*?".*?)data-title="(.*?)"(.*?)value="(.*?)"(.*?>)/ius',
                '/(<input.*?class=".*?t3js-form-field-slug-hidden.*?".*?)value="(.*?)"(.*?>)/ius',
                '/(<span class="input-group-addon">)(.*?)(<\/span>)/ius',
                '/(<span class="t3js-form-proposal-accepted hidden label label-success">Congrats, this page will look like )(.*?)(<span>)/ius',
                '/(<span class="t3js-form-proposal-different hidden label label-warning">Hmm, that is taken, how about )(.*?)(<span>)/ius',
            ],
            [
                '$1' . 'placeholder="' . $segments . '"' . '$3',
                '$1' . 'data-title="' . $segments . '"' . '$3' . 'value="' . $segments . '"' . '$5',
                '$1' . 'value="' . $segments . '"' . '$3',
                '$1' . $prefix . '$3',
                '$1' . $prefix . '$3',
                '$1' . $prefix . '$3'
            ],
            $html
        );
    }
}
