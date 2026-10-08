<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * The assistant roles a request may select.
     *
     * This is the single source of truth for the chat UI's role picker
     * (modeCatalog), the prompt built by ChatController::instructions(),
     * and the tools returned by ChatController::tools(). The modes come in
     * two families: read-only investigation modes (ask/plan/debug) that
     * never write, and execution modes (agent/orchestrate/code, research,
     * deep-search, study, security, art, chat) that do.
     *
     * @var list<string>
     */
    public const MODES = [
        'ask',
        'plan',
        'agent',
        'debug',
        'orchestrate',
        'chat',
        'code',
        'research',
        'deep-search',
        'study',
        'security',
        'art',
    ];

    //
}
