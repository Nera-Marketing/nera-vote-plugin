# Nera – Vote

Lets the team put a list of bottles in front of signed-in members for a fixed period and see which ones they prefer. It informs what the team lists next; it does not decide it.

## Language

**Vote**:
A time-boxed poll run by the team: a list of Options that members can tick between a start and an end date.
_Avoid_: Poll, round, survey, shortlist

**Option**:
One bottle on a Vote that a member can tick. It has a name, and optionally an image, a short description and a link. Members see the name; the rest is revealed on request. An Option can be hidden from Members without losing its Ballots. An Option that already has Ballots can only be hidden, never deleted.
_Avoid_: Item, candidate, choice

**Ballot**:
A signed-in member's tick for one Option of a Vote. A member never has two Ballots for the same Option, but may hold Ballots for any number of a Vote's Options, including none.
_Avoid_: Response, vote (that names the whole Vote)

**Scheduled / Open / Closed**:
A Vote is **Scheduled** before its start date, **Open** from its start to its end date, and **Closed** after. A Scheduled Vote shows Members only when it opens, not its Options. While Open a Member can tick and untick Options freely, and an unticked Option no longer counts. Once Closed every Ballot is locked, the results are final, and the Options remain visible read-only.
_Avoid_: Upcoming, active, finished, ended

**Results**:
A Vote's Options ranked from most to fewest Ballots. The team can always see them. Whether Members can see them, and whether that is already while the Vote is Open or only once it is Closed, is a setting on each Vote.
_Avoid_: Tally, scoreboard, leaderboard

**Member**:
A signed-in site user. Only a Member can cast a Ballot; a visitor who is not signed in sees a prompt to sign in instead.
_Avoid_: Voter, user, visitor
