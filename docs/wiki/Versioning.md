Written against v1.5.0.

A consumer pins this package by release, so each kind of change maps to one kind of release.

| Change | Release |
| --- | --- |
| A patch's content changes, same upstream pin | patch |
| A patch is added or retired, or the pin moves within the upstream major | minor |
| The pin moves to a new upstream major | major |

The upstream pin is `pestphp/pest-plugin-mutate` `5.0.2`, an exact version rather than a range, because every patch is cut against that release.

Reference: [Patches](Patches.md), [Adding or retiring a patch](Adding-or-retiring-a-patch.md).
