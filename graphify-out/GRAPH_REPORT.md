# Graph Report - .  (2026-04-23)

## Corpus Check
- 108 files · ~65,052 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 566 nodes · 647 edges · 71 communities detected
- Extraction: 86% EXTRACTED · 14% INFERRED · 0% AMBIGUOUS · INFERRED: 88 edges (avg confidence: 0.79)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_jQuery & Test Helpers|jQuery & Test Helpers]]
- [[_COMMUNITY_Kernel Core & Middleware|Kernel Core & Middleware]]
- [[_COMMUNITY_Documentation Concepts|Documentation Concepts]]
- [[_COMMUNITY_Security Provider Core|Security Provider Core]]
- [[_COMMUNITY_Configuration & Bootstrap|Configuration & Bootstrap]]
- [[_COMMUNITY_CORS Provider & Preflight|CORS Provider & Preflight]]
- [[_COMMUNITY_Error Handling & Renderers|Error Handling & Renderers]]
- [[_COMMUNITY_Test Controllers|Test Controllers]]
- [[_COMMUNITY_Pre-Authentication|Pre-Authentication]]
- [[_COMMUNITY_Cookie & Middleware Interface|Cookie & Middleware Interface]]
- [[_COMMUNITY_Kernel Web Tests|Kernel Web Tests]]
- [[_COMMUNITY_CORS Tests|CORS Tests]]
- [[_COMMUNITY_Security Tests|Security Tests]]
- [[_COMMUNITY_URL Generator & ZXC|URL Generator & ZXC]]
- [[_COMMUNITY_Documentation Governance|Documentation Governance]]
- [[_COMMUNITY_View Handler Interfaces|View Handler Interfaces]]
- [[_COMMUNITY_Smart View & JSON Handler|Smart View & JSON Handler]]
- [[_COMMUNITY_HTTP Exception & Route Collection|HTTP Exception & Route Collection]]
- [[_COMMUNITY_Pre-Auth Policy Abstract|Pre-Auth Policy Abstract]]
- [[_COMMUNITY_URL Matcher Wrapper|URL Matcher Wrapper]]
- [[_COMMUNITY_Fallback View Tests|Fallback View Tests]]
- [[_COMMUNITY_ELB Proxy Tests|ELB Proxy Tests]]
- [[_COMMUNITY_Group URL Matcher|Group URL Matcher]]
- [[_COMMUNITY_Firewall Interface|Firewall Interface]]
- [[_COMMUNITY_User Provider Abstract|User Provider Abstract]]
- [[_COMMUNITY_CORS Controller|CORS Controller]]
- [[_COMMUNITY_Argument Value Resolver|Argument Value Resolver]]
- [[_COMMUNITY_Session Listener|Session Listener]]
- [[_COMMUNITY_Advanced CORS Tests|Advanced CORS Tests]]
- [[_COMMUNITY_Cached URL Matcher|Cached URL Matcher]]
- [[_COMMUNITY_Twig Tests|Twig Tests]]
- [[_COMMUNITY_Test User Provider|Test User Provider]]
- [[_COMMUNITY_Fallback Controller|Fallback Controller]]
- [[_COMMUNITY_Twig Controller|Twig Controller]]
- [[_COMMUNITY_AWS Controller|AWS Controller]]
- [[_COMMUNITY_Chained Parameter Bag|Chained Parameter Bag]]
- [[_COMMUNITY_Issue Management|Issue Management]]
- [[_COMMUNITY_Session Provider|Session Provider]]
- [[_COMMUNITY_Security Config Tests|Security Config Tests]]
- [[_COMMUNITY_Twig Config Tests|Twig Config Tests]]
- [[_COMMUNITY_Test Access Rule|Test Access Rule]]
- [[_COMMUNITY_Test Auth Policy|Test Auth Policy]]
- [[_COMMUNITY_Test Pre-Authenticator|Test Pre-Authenticator]]
- [[_COMMUNITY_Exception Test Controller|Exception Test Controller]]
- [[_COMMUNITY_Exception Listener Wrapper|Exception Listener Wrapper]]
- [[_COMMUNITY_Twig Configuration|Twig Configuration]]
- [[_COMMUNITY_Router Configuration|Router Configuration]]
- [[_COMMUNITY_Security Configuration|Security Configuration]]
- [[_COMMUNITY_Access Rule Configuration|Access Rule Configuration]]
- [[_COMMUNITY_Firewall Configuration|Firewall Configuration]]
- [[_COMMUNITY_CORS Configuration|CORS Configuration]]
- [[_COMMUNITY_HTTP Configuration|HTTP Configuration]]
- [[_COMMUNITY_Uniqueness Violation Exception|Uniqueness Violation Exception]]
- [[_COMMUNITY_JSON Error Handler|JSON Error Handler]]
- [[_COMMUNITY_Null Entry Point|Null Entry Point]]
- [[_COMMUNITY_YAML File Loader|YAML File Loader]]
- [[_COMMUNITY_Route-Based Renderer Resolver|Route-Based Renderer Resolver]]
- [[_COMMUNITY_App Bootstrap|App Bootstrap]]
- [[_COMMUNITY_ZXC Index|ZXC Index]]
- [[_COMMUNITY_Test Bootstrap|Test Bootstrap]]
- [[_COMMUNITY_Test Entry|Test Entry]]
- [[_COMMUNITY_Twig Index|Twig Index]]
- [[_COMMUNITY_Security Index|Security Index]]
- [[_COMMUNITY_CORS Index|CORS Index]]
- [[_COMMUNITY_ELB Bootstrap|ELB Bootstrap]]
- [[_COMMUNITY_Security App Config 2|Security App Config 2]]
- [[_COMMUNITY_Security App Config|Security App Config]]
- [[_COMMUNITY_CORS App Config|CORS App Config]]
- [[_COMMUNITY_Advanced CORS App Config|Advanced CORS App Config]]
- [[_COMMUNITY_Twig App Config|Twig App Config]]
- [[_COMMUNITY_Twig App Config 2|Twig App Config 2]]

## God Nodes (most connected - your core abstractions)
1. `SilexKernel` - 19 edges
2. `TestController` - 16 edges
3. `SilexKernelWebTest` - 14 edges
4. `CrossOriginResourceSharingTest` - 13 edges
5. `WrappedExceptionInfo` - 13 edges
6. `SimpleSecurityProvider` - 12 edges
7. `CrossOriginResourceSharingStrategy` - 10 edges
8. `SilexKernel` - 10 edges
9. `SecurityServiceProviderTest` - 9 edges
10. `AuthController` - 9 edges

## Surprising Connections (you probably didn't know these)
- `Routing Configuration` --semantically_similar_to--> `Security Firewalls`  [INFERRED] [semantically similar]
  README.md → docs/AdvancedSecurityConfiguration.md
- `Route Placeholder` --feature_of--> `Routing Configuration`  [EXTRACTED]
  docs/AdvancedRoutingConfiguration.md → README.md
- `Route Requirements` --feature_of--> `Routing Configuration`  [EXTRACTED]
  docs/AdvancedRoutingConfiguration.md → README.md
- `Route Resource Importing` --feature_of--> `Routing Configuration`  [EXTRACTED]
  docs/AdvancedRoutingConfiguration.md → README.md
- `Route Caching` --feature_of--> `Routing Configuration`  [EXTRACTED]
  docs/AdvancedRoutingConfiguration.md → README.md

## Hyperedges (group relationships)
- **Bootstrap Configuration System** — config_routing, config_security, config_cors, config_twig, config_middlewares, config_providers, concept_view_handler, concept_error_handler, concept_injected_args, concept_trusted_proxies [EXTRACTED 1.00]
- **Security Authentication Flow** — security_policies, security_firewalls, security_access_rules, security_role_hierarchy, custom_policy_flow, concept_pre_authenticator, concept_user_provider, concept_request_sender [EXTRACTED 1.00]
- **Documentation Governance System** — doc_layer_state, doc_layer_manual, doc_layer_proposals, doc_layer_notes, doc_layer_changes, doc_layer_issues, concept_ssot, agents_instructions [EXTRACTED 0.95]

## Communities

### Community 0 - "jQuery & Test Helpers"
Cohesion: 0.04
Nodes (38): actualDisplay(), addCombinator(), ajaxConvert(), ajaxHandleResponses(), Animation(), augmentWidthOrHeight(), buildParams(), completed() (+30 more)

### Community 1 - "Kernel Core & Middleware"
Cohesion: 0.08
Nodes (5): AbstractMiddleware, AuthController, CacheableRouter, SilexKernel, SilexKernelTest

### Community 2 - "Documentation Concepts"
Cohesion: 0.07
Nodes (34): AWS CloudFront Trusted Proxy, AWS ELB Trusted Proxy, Error Handler, Injected Arguments, Pre-Authenticator Pattern, Request Sender (User Class), Trusted Proxies, User Provider Pattern (+26 more)

### Community 3 - "Security Provider Core"
Cohesion: 0.1
Nodes (9): getPattern(), getRequiredChannel(), getRequiredRoles(), getAuthenticationListener(), getAuthenticationProvider(), getAuthenticationType(), getEntryPoint(), SimpleFirewall (+1 more)

### Community 4 - "Configuration & Bootstrap"
Cohesion: 0.09
Nodes (4): CacheableRouterProvider, processConfiguration(), SimpleAccessRule, SimpleTwigServiceProvider

### Community 5 - "CORS Provider & Preflight"
Cohesion: 0.11
Nodes (3): CrossOriginResourceSharingProvider, CrossOriginResourceSharingStrategy, PrefilightResponse

### Community 6 - "Error Handling & Renderers"
Cohesion: 0.12
Nodes (4): DefaultHtmlRenderer, ExceptionWrapper, JsonApiRenderer, WrappedExceptionInfo

### Community 7 - "Test Controllers"
Cohesion: 0.17
Nodes (2): SubTestController, TestController

### Community 8 - "Pre-Authentication"
Cohesion: 0.13
Nodes (3): AbstractSimplePreAuthenticator, authenticateAndGetUser(), TestApiUser

### Community 9 - "Cookie & Middleware Interface"
Cohesion: 0.12
Nodes (4): after(), before(), ResponseCookieContainer, SimpleCookieProvider

### Community 10 - "Kernel Web Tests"
Cohesion: 0.12
Nodes (1): SilexKernelWebTest

### Community 11 - "CORS Tests"
Cohesion: 0.14
Nodes (1): CrossOriginResourceSharingTest

### Community 12 - "Security Tests"
Cohesion: 0.2
Nodes (1): SecurityServiceProviderTest

### Community 13 - "URL Generator & ZXC"
Cohesion: 0.24
Nodes (2): GroupUrlGenerator, ZxcController

### Community 14 - "Documentation Governance"
Cohesion: 0.22
Nodes (10): Agent Instructions, Socratic Review Gate, Spec Workflow, Single Source of Truth (SSOT), Changes Layer, Manual Layer, Notes Layer, Proposals Layer (+2 more)

### Community 15 - "View Handler Interfaces"
Cohesion: 0.25
Nodes (4): FallbackViewHandler, renderOnException(), renderOnSuccess(), resolveRequest()

### Community 16 - "Smart View & JSON Handler"
Cohesion: 0.28
Nodes (2): AbstractSmartViewHandler, JsonViewHandler

### Community 17 - "HTTP Exception & Route Collection"
Cohesion: 0.25
Nodes (2): HttpExceptionTest, InheritableRouteCollection

### Community 18 - "Pre-Auth Policy Abstract"
Cohesion: 0.36
Nodes (1): AbstractSimplePreAuthenticationPolicy

### Community 19 - "URL Matcher Wrapper"
Cohesion: 0.25
Nodes (3): CacheableRouterUrlMatcherWrapper, addToPrefiltersOrTransports(), createOptions()

### Community 20 - "Fallback View Tests"
Cohesion: 0.29
Nodes (1): FallbackViewHandlerTest

### Community 21 - "ELB Proxy Tests"
Cohesion: 0.29
Nodes (1): ElbTrustedProxyTest

### Community 22 - "Group URL Matcher"
Cohesion: 0.33
Nodes (1): GroupUrlMatcher

### Community 23 - "Firewall Interface"
Cohesion: 0.33
Nodes (0): 

### Community 24 - "User Provider Abstract"
Cohesion: 0.33
Nodes (1): AbstractSimplePreAuthenticateUserProvider

### Community 25 - "CORS Controller"
Cohesion: 0.6
Nodes (1): CorsController

### Community 26 - "Argument Value Resolver"
Cohesion: 0.4
Nodes (1): ExtendedArgumentValueResolver

### Community 27 - "Session Listener"
Cohesion: 0.5
Nodes (1): TestSessionListener

### Community 28 - "Advanced CORS Tests"
Cohesion: 0.5
Nodes (1): CrossOriginResourceSharingAdvancedTest

### Community 29 - "Cached URL Matcher"
Cohesion: 0.5
Nodes (1): ProjectUrlMatcher

### Community 30 - "Twig Tests"
Cohesion: 0.5
Nodes (1): TwigServiceProviderTest

### Community 31 - "Test User Provider"
Cohesion: 0.5
Nodes (1): TestApiUserProvider

### Community 32 - "Fallback Controller"
Cohesion: 0.5
Nodes (1): FallbackController

### Community 33 - "Twig Controller"
Cohesion: 0.5
Nodes (1): TwigController

### Community 34 - "AWS Controller"
Cohesion: 0.5
Nodes (1): AwsController

### Community 35 - "Chained Parameter Bag"
Cohesion: 0.5
Nodes (1): ChainedParameterBagDataProvider

### Community 36 - "Issue Management"
Cohesion: 0.5
Nodes (4): Issues Layer, L-Series Issues (Production Bugs), Release Issues (Stabilize Phase), Issue Severity System

### Community 37 - "Session Provider"
Cohesion: 0.67
Nodes (1): SessionServiceProvider

### Community 38 - "Security Config Tests"
Cohesion: 0.67
Nodes (1): SecurityServiceProviderConfigurationTest

### Community 39 - "Twig Config Tests"
Cohesion: 0.67
Nodes (1): TwigServiceProviderConfigurationTest

### Community 40 - "Test Access Rule"
Cohesion: 0.67
Nodes (1): TestAccessRule

### Community 41 - "Test Auth Policy"
Cohesion: 0.67
Nodes (1): TestAuthenticationPolicy

### Community 42 - "Test Pre-Authenticator"
Cohesion: 0.67
Nodes (1): TestApiUserPreAuthenticator

### Community 43 - "Exception Test Controller"
Cohesion: 0.67
Nodes (1): ExceptionTestController

### Community 44 - "Exception Listener Wrapper"
Cohesion: 0.67
Nodes (1): ExtendedExceptionListnerWrapper

### Community 45 - "Twig Configuration"
Cohesion: 0.67
Nodes (1): TwigConfiguration

### Community 46 - "Router Configuration"
Cohesion: 0.67
Nodes (1): CacheableRouterConfiguration

### Community 47 - "Security Configuration"
Cohesion: 0.67
Nodes (1): SecurityConfiguration

### Community 48 - "Access Rule Configuration"
Cohesion: 0.67
Nodes (1): SimpleAccessRuleConfiguration

### Community 49 - "Firewall Configuration"
Cohesion: 0.67
Nodes (1): SimpleFirewallConfiguration

### Community 50 - "CORS Configuration"
Cohesion: 0.67
Nodes (1): CrossOriginResourceSharingConfiguration

### Community 51 - "HTTP Configuration"
Cohesion: 0.67
Nodes (1): HttpConfiguration

### Community 52 - "Uniqueness Violation Exception"
Cohesion: 0.67
Nodes (1): UniquenessViolationHttpException

### Community 53 - "JSON Error Handler"
Cohesion: 0.67
Nodes (1): JsonErrorHandler

### Community 54 - "Null Entry Point"
Cohesion: 0.67
Nodes (1): NullEntryPoint

### Community 55 - "YAML File Loader"
Cohesion: 0.67
Nodes (1): InheritableYamlFileLoader

### Community 56 - "Route-Based Renderer Resolver"
Cohesion: 0.67
Nodes (1): RouteBasedResponseRendererResolver

### Community 57 - "App Bootstrap"
Cohesion: 1.0
Nodes (0): 

### Community 58 - "ZXC Index"
Cohesion: 1.0
Nodes (0): 

### Community 59 - "Test Bootstrap"
Cohesion: 1.0
Nodes (0): 

### Community 60 - "Test Entry"
Cohesion: 1.0
Nodes (0): 

### Community 61 - "Twig Index"
Cohesion: 1.0
Nodes (0): 

### Community 62 - "Security Index"
Cohesion: 1.0
Nodes (0): 

### Community 63 - "CORS Index"
Cohesion: 1.0
Nodes (0): 

### Community 64 - "ELB Bootstrap"
Cohesion: 1.0
Nodes (0): 

### Community 65 - "Security App Config 2"
Cohesion: 1.0
Nodes (0): 

### Community 66 - "Security App Config"
Cohesion: 1.0
Nodes (0): 

### Community 67 - "CORS App Config"
Cohesion: 1.0
Nodes (0): 

### Community 68 - "Advanced CORS App Config"
Cohesion: 1.0
Nodes (0): 

### Community 69 - "Twig App Config"
Cohesion: 1.0
Nodes (0): 

### Community 70 - "Twig App Config 2"
Cohesion: 1.0
Nodes (0): 

## Knowledge Gaps
- **23 isolated node(s):** `Symfony Components`, `Pimple DI Container`, `Twig Template Engine`, `Middlewares Configuration`, `Service Providers Configuration` (+18 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **Thin community `App Bootstrap`** (1 nodes): `app.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `ZXC Index`** (1 nodes): `index.zxc.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Test Bootstrap`** (1 nodes): `bootstrap.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Test Entry`** (1 nodes): `test.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Twig Index`** (1 nodes): `index.twig.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Security Index`** (1 nodes): `index.security.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `CORS Index`** (1 nodes): `index.cors.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `ELB Bootstrap`** (1 nodes): `elb.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Security App Config 2`** (1 nodes): `app.security2.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Security App Config`** (1 nodes): `app.security.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `CORS App Config`** (1 nodes): `app.cors.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Advanced CORS App Config`** (1 nodes): `app.cors-advanced.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Twig App Config`** (1 nodes): `app.twig.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Twig App Config 2`** (1 nodes): `app.twig2.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `SilexKernel` connect `Kernel Core & Middleware` to `Cookie & Middleware Interface`, `Kernel Web Tests`, `Configuration & Bootstrap`?**
  _High betweenness centrality (0.160) - this node is a cross-community bridge._
- **Why does `Animation()` connect `jQuery & Test Helpers` to `Kernel Core & Middleware`?**
  _High betweenness centrality (0.085) - this node is a cross-community bridge._
- **Why does `processConfiguration()` connect `Configuration & Bootstrap` to `Security Provider Core`?**
  _High betweenness centrality (0.078) - this node is a cross-community bridge._
- **What connects `Symfony Components`, `Pimple DI Container`, `Twig Template Engine` to the rest of the system?**
  _23 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `jQuery & Test Helpers` be split into smaller, more focused modules?**
  _Cohesion score 0.04 - nodes in this community are weakly interconnected._
- **Should `Kernel Core & Middleware` be split into smaller, more focused modules?**
  _Cohesion score 0.08 - nodes in this community are weakly interconnected._
- **Should `Documentation Concepts` be split into smaller, more focused modules?**
  _Cohesion score 0.07 - nodes in this community are weakly interconnected._