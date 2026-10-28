# Take-Home Task: Funeral Home Referral Finder

**Time:** 10 days
**AI tools:** Allowed. You must be able to explain and change every line of your code.

## The idea
Funeral homes talk to families about burial costs every day, and many of them don't offer insurance themselves. That makes them natural referral partners for final expense. There are thousands of them across the US, licensed state by state, and they open, close and change owners all the time. Our team needs a living, always-current view of them, not a list that is out of date the week after we pull it.

## What we want
Build a full-stack web application that does four things.

**1. Finds funeral homes every day, on its own**
- A scheduled crawler that runs at least once a day across public sources such as state funeral licensing boards, business directories, OpenStreetMap, official maps and places APIs, and funeral homes' own websites.
- It finds new funeral homes, refreshes existing ones and records what changed (new licence, closure, change of ownership, new website, new phone number).
- It matches the same funeral home across sources and removes duplicates.

**2. Builds a full profile for each funeral home**
Each funeral home gets its own page showing, where publicly available:
- Name, address, business phone numbers, business email, website and social pages
- Licence number, licence status and the licensed funeral director as published by the state board
- Ownership type: independent, family-owned, or part of a chain (such as Service Corporation International or Park Lawn), since independents are usually better partners
- Services offered (burial, cremation, pre-need planning) and whether they already sell insurance or pre-need products
- Reviews and signs of size and activity
- A partner score with a plain explanation of why it scored that way
- The source and date for every piece of data

**3. Writes outreach for each funeral home**
- Automatically generates a personalised first email, a phone-call script and two follow-ups for each funeral home, based on its profile. Outreach to an independent family business should sound different from outreach to a chain location.
- A person can edit and approve a campaign. Once approved, email sequences can send on a schedule. Calls and texts are never automated.
- Replies are logged against the funeral home, and sequences stop when someone replies or opts out.

**4. Runs the pipeline**
- Every funeral home moves through stages: found, contacted, replied, meeting, active partner, not interested.
- A map and dashboard by state and county, showing coverage, new finds today and follow-ups due.
- Filters by state, county, ownership type, partner score and stage.
- Users can add notes, assign a funeral home to an agent and log referrals received from each partner.

It must work across multiple states. Start with at least 5 states, each with a different licensing board format, and design it so adding a new state means writing one small source adapter, not changing the core app.

Which stack, sources, scoring and crawl design you use is up to you.

## Rules
- Collect public business information only. Staff names only as published in a business or licensing capacity. No personal home addresses or personal mobile numbers.
- Respect robots.txt, rate limits and each site's terms. Prefer official APIs and official state data where they exist.
- Every email includes an unsubscribe link, and opt-outs are honoured immediately (CAN-SPAM).
- Store secrets in environment variables, not in the repository.

## What to hand in
1. A deployed, working app with a login for us, and a short demo video, 5 minutes at most
2. Your Git repository with your full commit history
3. Evidence that the crawler has run on its own for at least 3 days, such as crawl logs and a "changed this week" view
4. A one-page write-up covering:
   - Your architecture, with a simple diagram
   - The sources you used per state, and the limits you ran into
   - How you match funeral homes across sources and detect changes
   - How you score partners and generate outreach
   - What you'd build next with another month
   - Where AI helped you, and where it got things wrong

## Before you start
Send us your questions within the first 48 hours. Asking good questions is part of the task.

## After you submit
A 30-minute call where you walk us through your work and make a small change live.
