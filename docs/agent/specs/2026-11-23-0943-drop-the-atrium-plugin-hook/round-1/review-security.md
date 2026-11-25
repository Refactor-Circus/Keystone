VERDICT: PASS

Security review: the diff removes code (registerAtriumPlugin, atrium surface prefix) and edits comments, docs and tests. It adds no entry point, input handling, secret, query or path use. The test file scan reads only fixed repo paths. History routes are untouched. The audit surface label change for atrium.* routes is documented in the CHANGELOG. No findings.
