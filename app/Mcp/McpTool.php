<?php

namespace App\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Client\Primitives\Tool as McpToolPrimitive;
use Stringable;
use Throwable;

/**
 * Presents one external MCP server tool to a Laravel AI agent.
 *
 * The MCP package and the AI package speak different tool contracts: this
 * adapter translates the MCP tool's raw JSON schema into the AI package's
 * schema types and forwards each invocation back to the server that declared
 * it. Failures are returned as readable text — the same contract the
 * workspace tools follow — so a server that misbehaves mid-turn produces a
 * message the model can react to instead of aborting the whole step.
 */
class McpTool implements Tool
{
    public function __construct(
        private readonly string $server,
        private readonly McpToolPrimitive $tool,
    ) {}

    /**
     * The name the server gave this tool, used in logs and errors.
     */
    public function name(): string
    {
        return $this->tool->name;
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return (string) ($this->tool->description ?? $this->tool->name);
    }

    /**
     * Execute the tool against its MCP server.
     */
    public function handle(Request $request): Stringable|string
    {
        try {
            $result = $this->tool->call($request->all());
        } catch (Throwable $e) {
            return "Error calling MCP tool [{$this->tool->name}] on server [{$this->server}]: {$e->getMessage()}";
        }

        $text = $result->text();

        if ($result->isError) {
            return 'Error: '.($text !== '' ? $text : 'the MCP server reported a failure.');
        }

        return $text !== '' ? $text : 'The MCP tool returned no text content.';
    }

    /**
     * Translate the server's raw JSON schema into AI schema types.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = $this->tool->inputSchema['properties'] ?? null;
        $required = $this->tool->inputSchema['required'] ?? null;

        if (! is_array($properties)) {
            return [];
        }

        $required = is_array($required) ? $required : [];
        $fields = [];

        foreach ($properties as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                continue;
            }

            $type = $this->typeFor($schema, $definition);

            $description = $definition['description'] ?? null;

            if (is_string($description)) {
                $type->description($description);
            }

            $enum = $definition['enum'] ?? null;

            if (is_array($enum) && $enum !== []) {
                $type->enum(array_values($enum));
            }

            if (in_array($name, $required, true)) {
                $type->required();
            }

            $fields[$name] = $type;
        }

        return $fields;
    }

    /**
     * Map a JSON schema type name onto the matching schema builder.
     *
     * @param  array<string, mixed>  $definition
     */
    private function typeFor(JsonSchema $schema, array $definition): Type
    {
        return match ($definition['type'] ?? 'string') {
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'boolean' => $schema->boolean(),
            'array' => $schema->array(),
            'object' => $schema->object(),
            default => $schema->string(),
        };
    }
}
