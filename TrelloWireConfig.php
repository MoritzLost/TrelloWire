<?php
namespace Processwire;

use ProcessWire\Inputfield;
use ProcessWire\InputfieldAsmSelect;
use ProcessWire\TrelloWire;

class TrelloWireConfig extends ModuleConfig
{
    public function getDefaults()
    {
        return [
            'TrelloWireActive' => true,
            'ApiKey' => '',
            'ApiToken' => '',
            'TargetBoard' => '',
            'TargetList' => '',
            'TrelloWireTemplates' => [],
            'CardTitle' => '{title}',
            'CardBody' => '{httpUrl}',
            'CardLabels' => [],
            'CardChecklistItems' => '',
            'CardChecklistTitle' => 'Checklist',
            'CardCreationTrigger' => TrelloWire::CREATE_ON_PUBLISHED,
            'CardUpdate' => false,

            'StatusChangeHidden' => TrelloWire::STATUS_CHANGE_NO_ACTION,
            'MoveListTargetHidden' => '',
            'RestoreOnReverseHidden' => false,

            'StatusChangeUnpublished' => TrelloWire::STATUS_CHANGE_NO_ACTION,
            'MoveListTargetUnpublished' => '',
            'RestoreOnReverseUnpublished' => false,

            'StatusChangeTrashed' => TrelloWire::STATUS_CHANGE_ARCHIVE,
            'MoveListTargetTrashed' => '',
            'RestoreOnReverseTrashed' => true,

            'StatusChangeDeleted' => TrelloWire::STATUS_CHANGE_ARCHIVE,
            'MoveListTargetDeleted' => '',
        ];
    }

    public function getInputFields()
    {
        $inputfields = parent::getInputfields();

        $TrelloWire = wire()->modules->get('TrelloWire');
        $currentApiKey = $TrelloWire->ApiKey;
        $currentApiToken = $TrelloWire->ApiToken;
        $hasApiKey = !empty($currentApiKey);
        $hasApiToken = !empty($currentApiToken);
        $hasValidToken = $hasApiKey && $hasApiToken && $TrelloWire->api()->isValidToken();
        $hasInvalidToken = $hasApiKey && $hasApiToken && !$hasValidToken;

        $TrelloWireActive = $this->buildInputfield('InputfieldCheckbox', 'TrelloWireActive', $this->_('Trello Wire status'), 34);
        $TrelloWireActive->label2 = $this->_('Activate module?');
        $TrelloWireActive->description = $this->_('Uncheck this to suspend all automatic operations that this module performs.');
        $TrelloWireActive->notes = $this->_('Use this switch if you want to temporarily disable the module without deleting your settings.');

        $ApiKey = $this->buildInputfield('InputfieldText', 'ApiKey', $this->_('Trello API Key'), 33, Inputfield::collapsedNever, true);
        $ApiKey->description = sprintf($this->_('You can [generate your API key here](%s).'), 'https://trello.com/app-key');

        $ApiToken = $this->buildInputfield('InputfieldText', 'ApiToken', $this->_('Trello API Token'), 33, Inputfield::collapsedNever, $hasApiKey);
        $tokenFieldDescription = $hasApiKey
            ? $this->_('[Generate your access token using this link](%s).')
            : $this->_('Please set your API key first.');
        $ApiToken->description = sprintf(
            $tokenFieldDescription,
            sprintf(
                'https://trello.com/1/authorize?expiration=%s&name=%s&scope=%s&key=%s&response_type=token',
                TrelloWire::TRELLO_API_TOKEN_EXPIRATION,
                TrelloWire::TRELLO_API_APP_NAME,
                implode(',', TrelloWire::TRELLO_API_PERMISSIONS),
                $currentApiKey
            )
        );
        if (!$hasApiKey) {
            $this->disableField($ApiToken);
        }
        if ($hasInvalidToken) {
            $ApiToken->error($this->_('The API token is invalid! It may have been deleted, expired or been revoked.'));
        }
        if ($hasValidToken) {
            $ApiToken->notes($this->_('SUCCESS: API token apears to be working!'));
        }

        $TrelloTargetSettings = wire()->modules->get('InputfieldFieldset');
        $TrelloTargetSettings->label = $this->_('Trello settings for new cards');
        $TrelloTargetSettings->description = $this->_('The main functionality of this module is to create cards on Trello whenever pages (of the specified templates) are created. Here you can control where those cards will be created and what they should contain. All those settings can be modified through hooks.');
        $TrelloTargetSettings->collapsed = Inputfield::collapsedNo;

        $CardSyncSettings = wire()->modules->get('InputfieldFieldset');
        $CardSyncSettings->label = $this->_('Card creation and update settings');
        $CardSyncSettings->description = $this->_('Those setting affect when new cards are created and updated based on page changes.');
        $CardSyncSettings->collapsed = Inputfield::collapsedNo;

        $StatusChanges = wire()->modules->get('InputfieldFieldset');
        $StatusChanges->label = $this->_('Status change handling');
        $StatusChanges->description = $this->_('For connected Trello workflows, it may be useful to modify cards based on status changes of the pages they belong to. Here you can select if you want to archive, delete or move the the card belonging to a page when that page is unpublished, hidden, trashed or deleted. If you choose to archive a card when one of those status changes occur, you can optionally restore the card when the status change is reversed (e.g. a previously hidden page is unhidden).');
        $StatusChanges->collapsed = Inputfield::collapsedNo;

        $inputfields->add($TrelloWireActive);
        $inputfields->add($ApiKey);
        $inputfields->add($ApiToken);

        $inputfields->add($TrelloTargetSettings);
        $inputfields->add($CardSyncSettings);
        $inputfields->add($StatusChanges);

        if (!$hasValidToken) {
            $settingsInactiveText = $this->_('Those settings will become available once you have set a valid API key & token.');
            $TrelloTargetSettings->description = $settingsInactiveText;
            $CardSyncSettings->description = $settingsInactiveText;
            $StatusChanges->description = $settingsInactiveText;
        }

        if ($hasValidToken) {
            $TrelloWireApi = $TrelloWire->api();

            $availableLists = $TrelloWire->TargetBoard ? $TrelloWireApi->lists($TrelloWire->TargetBoard) : null;

            $TargetBoard = $this->buildInputfield('InputfieldSelect', 'TargetBoard', $this->_('Trello target board'), 50, Inputfield::collapsedNever, true);
            $TargetBoard->description = $this->_('Select the default Trello board to add new cards to.');
            $availableBoards = $TrelloWireApi->boards();
            $TargetBoard->setOptions(array_combine(array_column($availableBoards, 'id'), array_column($availableBoards, 'name')));
            // @TODO: option groups for organizations

            $TargetList = $this->buildInputfield('InputfieldSelect', 'TargetList', $this->_('Trello target list'), 50, Inputfield::collapsedNever, !empty($TrelloWire->TargetBoard));
            $TargetList->description = $this->_('Select the default Trello list inside your selected board to add new cards to.');
            $TargetList->notes = $this->_('After changing the target board, submit the form to see available lists inside the board.');
            if (!$TrelloWire->TargetBoard) {
                $this->disableField($TargetList);
            } elseif ($availableLists) {
                $TargetList->setOptions(array_combine(array_column($availableLists, 'id'), array_column($availableLists, 'name')));
            } else {
                $TargetList->value = '';
                $TargetList->error($this->_('Error retrieving lists for this board. The board may have been deleted.'));
            }

            $TrelloWireTemplates = $this->buildInputfield('InputfieldAsmSelect', 'TrelloWireTemplates', $this->_('Templates to create trello cards for'), 34);
            $TrelloWireTemplates->description = $this->_('Select the templates you want to create Trello cards for.');
            $TrelloWireTemplates->notes = $this->_('Note that the module will do nothing if you select no templates here.');
            $TrelloWireTemplates->setAsmSelectOption('sortable', false);
            $this->addTemplatesToMultiSelect($TrelloWireTemplates);

            $CardTitle = $this->buildInputfield('InputfieldText', 'CardTitle', $this->_('Title / name for new cards'), 33, Inputfield::collapsedNever, true);
            $CardTitle->description = $this->_('This is passed through [wirePopulateStringTags](https://processwire.com/api/ref/functions/wire-populate-string-tags/), so you can include page field values with curly braces.');
            $CardTitle->notes = $this->_('Use `{title}` (with the curly braces) to just use the page title as the card name.');

            $CardBody = $this->buildInputfield('InputfieldTextarea', 'CardBody', $this->_('Body / description for new cards'), 33);
            $CardBody->description = $this->_('This is passed through [wirePopulateStringTags](https://processwire.com/api/ref/functions/wire-populate-string-tags/), so you can include page field values with curly braces.');
            $CardBody->notes = $this->_('Note that the body / description text does not support HTML, but you can use Markdown.');

            $CardLabels = $this->buildInputfield('InputfieldCheckboxes', 'CardLabels', $this->_('Card labels'), 34);
            $availableLabels = $TrelloWire->TargetBoard ? $TrelloWireApi->labels($TrelloWire->TargetBoard) : null;
            if ($availableLabels) {
                $CardLabels->addOptions(array_combine(
                    array_column($availableLabels, 'id'),
                    array_map(function ($labelData) {
                        if ($labelData->color && $labelData->name) {
                            return sprintf('%s (%s)', $labelData->name, $labelData->color);
                        } else {
                            return $labelData->name ?: $labelData->color;
                        }
                    }, $availableLabels)
                ));
                $CardLabels->description = $this->_('Note that available labels depend on the selected board. After you change the labels available in your board, you may have to update the selected labels here accordingly.');
            } else {
                $this->disableField($CardLabels);
                $CardLabels->description = $this->_('Available labels depend on the selected board. Please select a valid target board first.');
            }

            $CardChecklistItems = $this->buildInputfield('InputfieldTextarea', 'CardChecklistItems', $this->_('Card checklist items'), 33);
            $CardChecklistItems->description = $this->_('If you want new cards to contain a predefined checklist, enter checklist items here. One item per line. Each item is passed through [wirePopulateStringTags](https://processwire.com/api/ref/functions/wire-populate-string-tags/) individually.');
            $CardChecklistItems->notes = $this->_('**WARNING:** Creating many checklist items on new card will substantially increase the card creation time, because the Trello API only allows one item per request.');

            $CardChecklistTitle = $this->buildInputfield('InputfieldText', 'CardChecklistTitle', $this->_('Card checklist title'), 33);
            $CardChecklistTitle->description = $this->_('Set the title for the checklist for new cards.');
            $CardChecklistTitle->notes = $this->_('This is passed through [wirePopulateStringTags](https://processwire.com/api/ref/functions/wire-populate-string-tags/) as well.');
            $CardChecklistTitle->showIf("CardChecklistItems!=''");

            $TrelloTargetSettings->add($TargetBoard);
            $TrelloTargetSettings->add($TargetList);
            $TrelloTargetSettings->add($TrelloWireTemplates);
            $TrelloTargetSettings->add($CardTitle);
            $TrelloTargetSettings->add($CardBody);
            $TrelloTargetSettings->add($CardLabels);
            $TrelloTargetSettings->add($CardChecklistItems);
            $TrelloTargetSettings->add($CardChecklistTitle);

            $CardCreationTrigger = $this->buildInputfield('InputfieldRadios', 'CardCreationTrigger', $this->_('When should new cards be created?'), 50);
            $CardCreationTrigger->notes = $this->_('The first option is useful if you want to create cards manually using the module API.');
            $CardCreationTrigger->addOptions([
                TrelloWire::CREATE_NEVER => $this->_("Never create cards automatically"),
                TrelloWire::CREATE_ON_ADDED => $this->_('Create new cards whenever an applicable page is added'),
                TrelloWire::CREATE_ON_PUBLISHED => $this->_('Create new cards whenever an applicable page is published'),
            ]);

            $CardUpdate = $this->buildInputfield('InputfieldCheckbox', 'CardUpdate', $this->_('Page update handling'), 50);
            $CardUpdate->label2 = $this->_('Update Trello cards when pages are updated');
            $CardUpdate->description = $this->_('When a page with a reference to a card is saved, update the card on Trello?');
            $CardUpdate->notes = $this->_("Currently, only the card's title and body are updated. Note that this will overwrite manual changes done on Trello.");

            $CardSyncSettings->add($CardCreationTrigger);
            $CardSyncSettings->add($CardUpdate);

            $StatusChangeLabels = $this->statusChangeFieldLabels();
            foreach (['Hidden', 'Unpublished', 'Trashed', 'Deleted'] as $status) {
                $fieldName = "StatusChange{$status}";
                $StatusChangeOptions = $this->buildInputfield('InputfieldRadios', $fieldName, $StatusChangeLabels[$status]['label'], 34, Inputfield::collapsedNever, true);
                if (!empty($StatusChangeLabels[$status]['notes'])) {
                    $StatusChangeOptions->notes = $StatusChangeLabels[$status]['notes'];
                }
                $StatusChangeOptions->addOptions([
                    TrelloWire::STATUS_CHANGE_NO_ACTION => $this->_('Do nothing'),
                    TrelloWire::STATUS_CHANGE_MOVE => $this->_('Move the card to a different list'),
                    TrelloWire::STATUS_CHANGE_ARCHIVE => $this->_('Archive the card'),
                    TrelloWire::STATUS_CHANGE_DELETE => $this->_('Delete the card (irreversibly)'),
                ]);
                $StatusChanges->add($StatusChangeOptions);

                $MoveListTarget = $this->buildInputfield('InputfieldSelect', "MoveListTarget{$status}", $this->_('Select the list to move the card to'), 33);
                if ($availableLists) {
                    $MoveListTarget->setOptions(array_combine(array_column($availableLists, 'id'), array_column($availableLists, 'name')));
                } else {
                    $this->disableField($MoveListTarget);
                    $MoveListTarget->description = $this->_('Please select a valid target board first.');
                }
                $MoveListTarget->showIf(sprintf('%s=%s', $fieldName, TrelloWire::STATUS_CHANGE_MOVE));
                $MoveListTarget->requiredIf(sprintf('%s=%s', $fieldName, TrelloWire::STATUS_CHANGE_MOVE));
                $StatusChanges->add($MoveListTarget);

                // deleted pages cannot be restored
                if (!in_array($status, ['Deleted'])) {
                    $RestoreOnReverse = $this->buildInputfield('InputfieldCheckbox', "RestoreOnReverse{$status}", $this->_('Optional card restoration'), 33);
                    $RestoreOnReverse->label2 = $StatusChangeLabels[$status]['restoreLabel'];
                    $RestoreOnReverse->showIf(sprintf('%s=%s', $fieldName, TrelloWire::STATUS_CHANGE_ARCHIVE));
                    $StatusChanges->add($RestoreOnReverse);
                }
            }
        }

        return $inputfields;
    }

    /**
     * Build an Inputfield object. Pass null for any option to skip it.
     *
     * @param string $module            The inputfield module to use.
     * @param string|null $name         The name of the input.
     * @param string|null $label        The field label.
     * @param integer|null $columnWidth The column width.
     * @param integer|null $collapsed   The collapsed status (@see Inputfield).
     * @param boolean|null $required    Is this field required?
     * @return Inputfield
     */
    protected function buildInputfield(
        string $module = 'InputfieldText',
        ?string $name = null,
        ?string $label = null,
        ?int $columnWidth = 100,
        ?int $collapsed = Inputfield::collapsedNever,
        ?bool $required = null
    ): Inputfield {
        $inputfield = wire()->modules->get($module);
        if ($name) $inputfield->name = $name;
        if ($label) $inputfield->label = $label;
        if (null !== $columnWidth) $inputfield->columnWidth = $columnWidth;
        if (null !== $collapsed) $inputfield->collapsed = $collapsed;
        if (null !== $required) $inputfield->required = $required;
        return $inputfield;
    }

    /**
     * Disable an inputfield by adding a disabled attribute and adding some styling.
     *
     * @param Inputfield $inputfield
     * @return void
     */
    protected function disableField(Inputfield $inputfield): void
    {
        // @TODO: cleaner "disabled" status & styling
        $inputfield->attr('disabled', 'disabled');
        $inputfield->attr('style', 'background: #ccc; cursor: not-allowed;');
    }

    /**
     * Add options for all templates to an inputfield. Option values are template
     * names, display titles use the template's label if it has one. Includes system
     * templates in advanced mode.
     *
     * @param InputfieldAsmSelect $inputfield
     * @return void
     */
    protected function addTemplatesToMultiSelect(InputfieldSelectMultiple $inputfield): void
    {
        foreach (wire('templates') as $template) {
            if (!wire('config')->advanced && ($template->flags & Template::flagSystem)) continue;
            $displayName = $template->label ? "{$template->label} ({$template->name})" : $template->name;
            $inputfield->addOption($template->name, $displayName);
        }
    }

    /**
     * Get the labels used for the status change options.
     *
     * @return array
     */
    protected function statusChangeFieldLabels(): array
    {
        return [
            'Hidden' => [
                'label' => $this->_('What should happen if the page is hidden?'),
                'restoreLabel' => $this->_('Restore the card when the page is unhidden?'),
            ],
            'Unpublished' => [
                'label' => $this->_('What should happen if the page is unpublished?'),
                'restoreLabel' => $this->_('Restore the card when the page is published again?'),
            ],
            'Trashed' => [
                'label' => $this->_('What should happen if the page is trashed?'),
                'restoreLabel' => $this->_('Restore the card when the page is restored from the trash?'),
            ],
            'Deleted' => [
                'label' => $this->_('What should happen if the page is deleted?'),
                'notes' => $this->_("Deleted pages can't be restored, so it's not possible to automatically restore the card."),
            ],
        ];
    }
}
