<?php
namespace Processwire;

use ProcessWire\Inputfield;
use ProcessWire\InputfieldAsmSelect;
use ProcessWire\TrelloWireApi;

class TrelloWireConfig extends ModuleConfig
{
    public function getDefaults()
    {
        return [
            'ApiKey' => '',
            'ApiToken' => '',
            'CardPageSelector' => '',
            'CardTitle' => 'title',
            'CardBody' => '',
        ];
    }

    public function getInputFields()
    {
        $inputfields = parent::getInputfields();

        $TrelloWire = wire()->modules->get('TrelloWire');
        $TrelloWireApi = $TrelloWire->api();
        $currentApiKey = $TrelloWire->ApiKey;
        $currentToken = $TrelloWire->ApiToken;
        $hasApiKey = !empty($currentApiKey);
        $hasApiToken = !empty($currentToken);
        $isInvalidToken = $hasApiToken ? !$TrelloWireApi->isValidToken() : false;

        $ApiKey = wire()->modules->get('InputfieldText');
        $ApiKey->name = 'ApiKey';
        $ApiKey->label = $this->_('Trello API key');
        $ApiKey->required = true;
        $ApiKey->description = sprintf($this->_('You can [generate your API key here](%s).'), 'https://trello.com/app-key');
        $ApiKey->columnWidth = 50;
        $ApiKey->collapsed = Inputfield::collapsedNever;

        $ApiToken = wire()->modules->get('InputfieldText');
        $ApiToken->name = 'ApiToken';
        $ApiToken->label = $this->_('Trello API token');
        $ApiToken->required = $hasApiKey;
        if (!$hasApiKey) {
            // @TODO: cleaner "disabled" status & styling
            $ApiToken->attr('disabled', 'disabled');
            $ApiToken->attr('style', 'background: #ccc; cursor: not-allowed;');
        }
        $tokenFieldDescription = $hasApiKey
            ? $this->_('[Generate your access token using this link](%s).')
            : $this->_('Please set your API key first.');
        $ApiToken->description = sprintf(
            $tokenFieldDescription,
            sprintf(
                'https://trello.com/1/authorize?expiration=never&name=%s&scope=%s&response_type=token&key=%s',
                'TrelloWire', // @TODO: Make dynamic
                'read,write', // @TODO: everything needed? make dynamic!
                $currentApiKey,
            )
        );
        if ($isInvalidToken) {
            $ApiToken->error($this->_('The API token is invalid! It may have been deleted, expired or been revoked.'));
        }
        if ($hasApiKey && !$isInvalidToken) {
            $ApiToken->notes($this->_('SUCCESS: API token apears to be working!'));
        }
        $ApiToken->columnWidth = 50;
        $ApiToken->collapsed = Inputfield::collapsedNever;

        $inputfields->add($ApiKey);
        $inputfields->add($ApiToken);

        // @TODO: Add usage notes / descriptions to all fields
        if ($hasApiKey && $hasApiToken && !$isInvalidToken) {
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
            // @TODO: if the selected board doesnt have this list, delete the currently saved option
            $TargetList->columnWidth = 50;
            $TargetList->collapsed = Inputfield::collapsedNever;

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
    
            $CardBody = wire()->modules->get('InputfieldText');
            $CardBody->name = 'CardBody';
            $CardBody->label = $this->_('Card body (field selector)');
            $CardBody->columnWidth = 33;
            $CardBody->collapsed = Inputfield::collapsedNever;

            $inputfields->add($TargetBoard);
            $inputfields->add($TargetList);

            $inputfields->add($TrelloWireTemplates);
            $inputfields->add($CardTitle);
            $inputfields->add($CardBody);
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
}
