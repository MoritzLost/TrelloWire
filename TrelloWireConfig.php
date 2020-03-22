<?php
namespace Processwire;

use ProcessWire\Inputfield;
use ProcessWire\InputfieldAsmSelect;
use ProcessWire\TrelloWire;
use ProcessWire\TrelloWireApi;
use Throwable;

class TrelloWireConfig extends ModuleConfig
{
    public function getDefaults()
    {
        return [
            'ApiKey' => '',
            'ApiToken' => '',
            'TargetBoard' => '',
            'TargetList' => '',
            'TrelloWireTemplates' => [],
            'CardTitle' => 'title',
            'CardBody' => '',
            'CardCreationTrigger' => TrelloWire::CREATE_ON_PUBLISHED,
            'CardUpdate' => true,
            'StatusChangeHidden' => TrelloWire::STATUS_CHANGE_NO_ACTION,
            'MoveListTargetHidden' => '',
            'RestoreOnReverseHidden' => false,
            'StatusChangeUnpublished' => TrelloWire::STATUS_CHANGE_NO_ACTION,
            'MoveListTargetUnpublished' => '',
            'RestoreOnReverseUnpublished' => false,
            'StatusChangeTrashed' => TrelloWire::STATUS_CHANGE_ARCHIVE,
            'MoveListTargetTrashed' => '',
            'RestoreOnReverseTrashed' => true,
            'StatusChangeDeleted' => TrelloWire::STATUS_CHANGE_DELETE,
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
        $hasInvalidToken = $hasApiToken ? !$TrelloWire->api()->isValidToken() : false;

        $ApiKey = wire()->modules->get('InputfieldText');
        $ApiKey->name = 'ApiKey';
        $ApiKey->label = $this->_('Trello API key');
        $ApiKey->columnWidth = 50;
        $ApiKey->collapsed = Inputfield::collapsedNever;
        $ApiKey->required = true;
        $ApiKey->description = sprintf($this->_('You can [generate your API key here](%s).'), 'https://trello.com/app-key');

        $ApiToken = wire()->modules->get('InputfieldText');
        $ApiToken->name = 'ApiToken';
        $ApiToken->label = $this->_('Trello API token');
        $ApiToken->columnWidth = 50;
        $ApiToken->collapsed = Inputfield::collapsedNever;
        $ApiToken->required = $hasApiKey;
        $tokenFieldDescription = $hasApiKey
            ? $this->_('[Generate your access token using this link](%s).')
            : $this->_('Please set your API key first.');
        $ApiToken->description = sprintf(
            $tokenFieldDescription,
            sprintf(
                'https://trello.com/1/authorize?expiration=never&name=%s&scope=%s&response_type=token&key=%s',
                'TrelloWire', // @TODO: Make dynamic
                'read,write', // @TODO: everything needed? make dynamic!
                $currentApiKey
            )
        );
        if (!$hasApiKey) {
            $this->disableField($ApiToken);
        }
        if ($hasInvalidToken) {
            $ApiToken->error($this->_('The API token is invalid! It may have been deleted, expired or been revoked.'));
        }
        if ($hasApiToken && !$hasInvalidToken) {
            $ApiToken->notes($this->_('SUCCESS: API token apears to be working!'));
        }

        $inputfields->add($ApiKey);
        $inputfields->add($ApiToken);

        // @TODO: Add usage notes / descriptions to all fields
        if ($hasApiKey && $hasApiToken && !$hasInvalidToken) {
            $TrelloWireApi = $TrelloWire->api();

            try {
                $availableLists = $TrelloWireApi->lists($TrelloWire->TargetBoard);
            } catch (Throwable $e) {
                $availableLists = null;
            }

            $TargetBoard = wire()->modules->get('InputfieldSelect');
            $TargetBoard->name = 'TargetBoard';
            $TargetBoard->label = $this->_('Trello target board');
            $TargetBoard->description = $this->_('Select the default Trello board to add new cards to.');
            $TargetBoard->columnWidth = 50;
            $TargetBoard->collapsed = Inputfield::collapsedNever;
            $availableBoards = $TrelloWireApi->boards();
            $TargetBoard->setOptions(array_combine(array_column($availableBoards, 'id'), array_column($availableBoards, 'name')));
            // @TODO: option groups for organizations

            $TargetList = wire()->modules->get('InputfieldSelect');
            $TargetList->name = 'TargetList';
            $TargetList->label = $this->_('Trello target list');
            $TargetList->description = $this->_('Select the default Trello list inside your selected board to add new cards to.');
            $TargetList->notes = $this->_('After changing the target board, submit the form to see available lists inside the board.');
            $TargetList->columnWidth = 50;
            $TargetList->collapsed = Inputfield::collapsedNever;
            // @TODO: if the selected board doesnt have this list, delete the currently saved option
            if (!$TrelloWire->TargetBoard) {
                $this->disableField($TargetList);
            } else {
                if (null !== $availableLists) {
                    $TargetList->setOptions(array_combine(array_column($availableLists, 'id'), array_column($availableLists, 'name')));
                } else {
                    $TargetList->error($this->_('Error retrieving lists for this board. The board may have been deleted.'));
                }
            }

            $TrelloWireTemplates = wire()->modules->get('InputfieldAsmSelect');
            $TrelloWireTemplates->name = 'TrelloWireTemplates';
            $TrelloWireTemplates->label = $this->_('Templates to create trello cards for.');
            $TrelloWireTemplates->setAsmSelectOption('sortable', false);
            $TrelloWireTemplates->columnWidth = 34;
            $TrelloWireTemplates->collapsed = Inputfield::collapsedNever;
            $this->asmSelectAddTemplates($TrelloWireTemplates);

            $CardTitle = wire()->modules->get('InputfieldText');
            $CardTitle->name = 'CardTitle';
            $CardTitle->required = true;
            $CardTitle->label = $this->_('Card title (field selector)');
            $CardTitle->columnWidth = 34;
            $CardTitle->collapsed = Inputfield::collapsedNever;
    
            $CardBody = wire()->modules->get('InputfieldTextarea');
            $CardBody->name = 'CardBody';
            $CardBody->label = $this->_('Card body (field selector)');
            $CardBody->columnWidth = 33;
            $CardBody->collapsed = Inputfield::collapsedNever;

            $TrelloTargetSettings = wire()->modules->get('InputfieldFieldset');
            $TrelloTargetSettings->label = $this->_('Trello settings for new cards');
            $TrelloTargetSettings->collapsed = Inputfield::collapsedNo;

            $TrelloTargetSettings->add($TargetBoard);
            $TrelloTargetSettings->add($TargetList);
            $TrelloTargetSettings->add($TrelloWireTemplates);
            $TrelloTargetSettings->add($CardTitle);
            $TrelloTargetSettings->add($CardBody);

            $CardCreationTrigger = wire()->modules->get('InputfieldRadios');
            $CardCreationTrigger->name = 'CardCreationTrigger';
            $CardCreationTrigger->label = $this->_('When should new cards be created?');
            $CardCreationTrigger->addOptions([
                TrelloWire::CREATE_NEVER => $this->_("Never create cards automatically"),
                TrelloWire::CREATE_ON_ADDED => $this->_('Create new cards whenever an applicable page is added'),
                TrelloWire::CREATE_ON_PUBLISHED => $this->_('Create new cards whenever an applicable page is published'),
            ]);
            $CardCreationTrigger->columnWidth = 50;
            $CardCreationTrigger->collapsed = Inputfield::collapsedNever;

            $CardUpdate = wire()->modules->get('InputfieldCheckbox');
            $CardUpdate->name = 'CardUpdate';
            $CardUpdate->label = $this->_('Page update handling');
            $CardUpdate->label2 = $this->_('Update Trello cards when pages are updated');
            $CardUpdate->description = $this->_('When a page with a reference to a card is saved, update the card on Trello?');
            $CardUpdate->columnWidth = 50;
            $CardUpdate->collapsed = Inputfield::collapsedNever;

            $CardSyncSettings = wire()->modules->get('InputfieldFieldset');
            $CardSyncSettings->label = $this->_('Card creation and update settings');
            $CardSyncSettings->collapsed = Inputfield::collapsedNo;

            $CardSyncSettings->add($CardCreationTrigger);
            $CardSyncSettings->add($CardUpdate);

            $StatusChanges = wire()->modules->get('InputfieldFieldset');
            $StatusChanges->label = $this->_('Status change handling');
            $StatusChanges->collapsed = Inputfield::collapsedNo;

            $StatusChangeLabels = $this->statusChangeFieldLabels();
            foreach (['Hidden', 'Unpublished', 'Trashed', 'Deleted'] as $status) {
                $fieldName = "StatusChange{$status}";
                $StatusChangeOptions = wire()->modules->get('InputfieldRadios');
                $StatusChangeOptions->name = $fieldName;
                $StatusChangeOptions->label = $StatusChangeLabels[$status]['label'];
                $StatusChangeOptions->addOptions([
                    TrelloWire::STATUS_CHANGE_NO_ACTION => $this->_('Do nothing'),
                    TrelloWire::STATUS_CHANGE_MOVE => $this->_('Move the card to a different list'),
                    TrelloWire::STATUS_CHANGE_ARCHIVE => $this->_('Archive the card'),
                    TrelloWire::STATUS_CHANGE_DELETE => $this->_('Delete the card (irreversibly)'),
                ]);
                $StatusChangeOptions->required = true;
                $StatusChangeOptions->columnWidth = 34;
                $StatusChangeOptions->collapsed = Inputfield::collapsedNever;
                $StatusChanges->add($StatusChangeOptions);

                $MoveListTarget = wire()->modules->get('InputfieldSelect');
                $MoveListTarget->name = "MoveListTarget{$status}";
                $MoveListTarget->label = $this->_('Select the list to move the card to');
                $MoveListTarget->columnWidth = 33;
                $MoveListTarget->collapsed = Inputfield::collapsedNever;
                $MoveListTarget->setOptions(array_combine(array_column($availableLists, 'id'), array_column($availableLists, 'name')));
                $MoveListTarget->showIf(sprintf('%s=%s', $fieldName, TrelloWire::STATUS_CHANGE_MOVE));
                $MoveListTarget->requiredIf(sprintf('%s=%s', $fieldName, TrelloWire::STATUS_CHANGE_MOVE));
                $StatusChanges->add($MoveListTarget);

                if (!in_array($status, ['Deleted'])) {
                    $RestoreOnReverse = wire()->modules->get('InputfieldCheckbox');
                    $RestoreOnReverse->name = "RestoreOnReverse{$status}";
                    $RestoreOnReverse->label = $StatusChangeLabels[$status]['restoreLabel'];
                    $RestoreOnReverse->columnWidth = 33;
                    $RestoreOnReverse->collapsed = Inputfield::collapsedNever;
                    $RestoreOnReverse->showIf(sprintf('%s=%s', $fieldName, TrelloWire::STATUS_CHANGE_ARCHIVE));
                    $StatusChanges->add($RestoreOnReverse);
                }
            }

            $inputfields->add($TrelloTargetSettings);
            $inputfields->add($CardSyncSettings);
            $inputfields->add($StatusChanges);
        }

        return $inputfields;
    }

    protected function asmSelectAddTemplates(InputfieldAsmSelect $asm): void
    {
        foreach (wire('templates') as $template) {
            if (!wire('config')->advanced && ($template->flags & Template::flagSystem)) continue;
            $displayName = $template->label ? "{$template->label} ({$template->name})" : $template->name;
            $asm->addOption($template->id, $displayName);
        }
    }

    protected function disableField(Inputfield $inputfield): void
    {
        // @TODO: cleaner "disabled" status & styling
        $inputfield->attr('disabled', 'disabled');
        $inputfield->attr('style', 'background: #ccc; cursor: not-allowed;');
    }

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
            ],
        ];
    }
}
