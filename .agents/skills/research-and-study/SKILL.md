---
name: research-and-study
description: "Producing research answers and study material in Whale. Use when implementing or prompting the research agent (web search, citations) or the study agent (explanations, study plans, flashcards, quizzes), or when a feature needs source-backed answers or learning content."
license: MIT
metadata:
  author: whale
---

# Research & Study

## Research

The `ResearchAgent` uses the provider's own `WebSearch` and `WebFetch` tools, so
it needs a provider that supports them (OpenAI, Anthropic or Gemini). If a
provider lacks web tools the agent will answer from memory, which is exactly
what it must not do — check the configured provider before relying on it.

Rules the agent follows:
- Search before answering; never rely on memory for facts that change.
- Cite each claim with a markdown link.
- State disagreement between sources, and say plainly when something cannot be
  verified.

When wiring research into a new surface, keep the same contract: pass a
specific question, not a vague topic.

## Study

The `StudyAgent` turns a topic or pasted source into learning material:
a plain-language summary, progressive sections, flashcards as a `Front`/`Back`
table, and quizzes with an answers section.

Because sub-agents run in isolation, any source material must be included in the
delegation brief. Do not assume the sub-agent can fetch the document itself.

## Prompting checklist

- One question or one topic per delegation.
- Include the audience and the desired depth.
- Ask for the output shape (table, sections, quiz) explicitly.
- Keep the coordinator's own reply short — summarise, link, and let the
  sub-agent's structure carry the detail.
