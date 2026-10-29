# Temporary deepmerge security patch

This private development package contains the CommonJS distribution and TypeScript
interface from `deepmerge` 4.3.1 (MIT; see LICENSE). It preserves the public API used
by Jest 29 while addressing CVE-2026-93753 / GHSA-gcqx-w48m-w2h4. It is a local
PublishPress patch, not an upstream release.

The root development dependency and npm override force Jest to resolve this copy,
including installations using `npm ci --ignore-scripts`. No postinstall patching
is needed. The lockfile records the local package's name and location.

The patch rejects `__proto__`, `constructor`, and `prototype` in both target and
source objects before copying or recursion. Assignments define own data properties
rather than invoking inherited setters. Ordinary nested objects, arrays, symbols,
custom merge callbacks, and cloning behavior are preserved.

References:
- https://github.com/advisories/GHSA-gcqx-w48m-w2h4
- https://github.com/TehShrike/deepmerge/issues/273
- https://github.com/TehShrike/deepmerge/tree/v4.3.1

Run `npm run test:security` to test the package resolved by Jest, including the
reported exploit cases. The package is excluded from WordPress release artifacts.

Remove this directory, the direct `deepmerge` development dependency, and the
`deepmerge` override when upstream publishes a verified fix or Jest no longer
requires the affected package. Regenerate the lockfile and rerun the security
tests when replacing this patch.
