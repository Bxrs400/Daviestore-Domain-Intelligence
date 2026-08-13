# AWS AgentCore web search

`AwsAgentCoreWebSearchProvider` is a direct Laravel discovery provider. It calls the configured AgentCore Gateway server-side and returns normalized candidates to `DomainDiscoveryService`; candidates continue through the existing normalization, deduplication, validation, scoring, technology detection, and classification pipeline.

## Dependency

Install the PHP AWS SDK in the existing Laravel backend:

```bash
composer require aws/aws-sdk-php
```

## Configuration

```env
AWS_REGION=your-aws-region
AWS_AGENTCORE_WEB_SEARCH_ENABLED=true
AWS_AGENTCORE_GATEWAY_URL=https://your-gateway-endpoint
AWS_AGENTCORE_GATEWAY_ID=your-gateway-id
AWS_AGENTCORE_WEB_SEARCH_TOOL=web_search
```

Authentication is explicit through `AWS_AGENTCORE_AUTH_MODE`. `iam` is the default and signs MCP Gateway requests with AWS Signature Version 4 using the standard AWS credential chain and `AWS_REGION`. `jwt` is supported only when the Gateway is configured for JWT/OAuth inbound authorization and uses the backend-only `AWS_AGENTCORE_GATEWAY_TOKEN`. AWS credentials and tokens are never sent to the browser.

The IAM implementation uses `aws/aws-sdk-php` (`Aws\\Credentials\\CredentialProvider` and `Aws\\Signature\\SignatureV4`). The MCP flow is `initialize`, `tools/list`, then `tools/call`; the provider discovers the actual tool whose name is `WebSearch` rather than assuming a fixed `web_search` name.

Google remains optional. GitHub is selected automatically only for technical queries. `AWS_REGION` is infrastructure configuration and does not constrain worldwide search; user location fields are included in bounded query expansion only when the requested scope is not worldwide.

The Gateway service role must be granted the least-privilege permissions required by the configured AWS-owned tool and region. Verify these exact actions with the Gateway resource owner before testing:

```text
bedrock-agentcore:InvokeGateway
bedrock-agentcore:InvokeWebSearch
```

Do not modify IAM automatically.

The diagnostic endpoint is:

```text
GET /api/v1/discovery/providers/aws
```

It reports readiness without returning secrets.
