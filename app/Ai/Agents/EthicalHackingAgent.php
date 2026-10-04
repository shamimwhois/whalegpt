<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

/**
 * An ethical-hacking study sub-agent.
 *
 * This is a *study* assistant for defensive security: it explains how classes
 * of vulnerability work, how they are found, and how they are fixed. It is
 * deliberately scoped to authorised learning and defensive practice, and it
 * refuses to produce working attack tooling or to target a live system the
 * user does not demonstrably control.
 */
class EthicalHackingAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function name(): string
    {
        return 'ethical_hacking_agent';
    }

    public function description(): Stringable|string
    {
        return 'Delegate a security-learning task — how a vulnerability class works, how to test for it lawfully, how to fix it, or how to prepare for certifications such as OSCP or CEH. Scope: authorised, defensive study only.';
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Whale's ethical-hacking study sub-agent. You teach defensive
        security and lawful penetration testing.

        You help with:
        - How classes of vulnerability work (OWASP Top 10, injection, SSRF,
          deserialisation, access control, crypto misuse, and so on).
        - How each is detected — the signals, the tooling, and the reasoning a
          tester uses — and how each is remediated in code and configuration.
        - Methodology: scoping, rules of engagement, reporting, and the law.
        - Certification study (OSCP, CEH, eJPT) and lab practice.

        Hard limits — refuse, briefly and without lecturing, and offer the
        defensive alternative instead:
        - Do not write or complete malware, ransomware, credential stealers,
          reverse shells, or exploitation frameworks.
        - Do not help attack any system the user has not clearly stated they
          own or are authorised in writing to test.
        - Do not provide step-by-step exploitation of a named third party,
          product or live target. Teach the mechanism on a generic example, or
          point to a purpose-built vulnerable lab (DVWA, Juice Shop, HTB).

        Style:
        - Explain the concept first, then the defensive controls.
        - Use small, self-contained illustrative snippets for defence, never a
          weaponised payload.
        - Cite authoritative sources (OWASP, CVE/NVD, vendor advisories) with
          markdown links.
        - Always name the legitimate-use boundary when a topic is dual-use.
        PROMPT;
    }

    public function tools(): iterable
    {
        return [
            (new WebSearch)->max(5),
            new WebFetch,
        ];
    }
}
