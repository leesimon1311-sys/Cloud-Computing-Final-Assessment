# Presentation Outline (Section B, 20 marks)

Maximum **15 slides**. Session is **15 minutes: 10 presenting, 5 for questions**.

Ten minutes over 13 content slides is roughly **45 seconds per slide**. That is the real
constraint — build for it rather than discovering it on the day.

---

## Slide plan

| # | Slide | Content | Time |
|---|---|---|---|
| 1 | Title | System name, your name, student ID, module, date | 15s |
| 2 | The problem | Why manual/file-based stock tracking fails: no concurrent access, no alerts, no audit trail, data lost with the file | 45s |
| 3 | System overview | One architecture diagram: browser → Apache/PHP → database, drawn once for both environments | 60s |
| 4 | Purpose, objectives, scope | The same three objectives as the report. Say what is out of scope | 45s |
| 5 | Main functions | 5–6 bullets, screenshot of the dashboard alongside | 45s |
| 6 | **Live demo** | Sign in → add a product → low-stock alert → search → cloud URL | 120s |
| 7 | Local deployment | XAMPP, services, database account, localhost and LAN IP. Screenshot A1 + B2 | 45s |
| 8 | Cloud deployment | GitHub → blueprint → Docker build → live. Screenshot C3 + C4 | 45s |
| 9 | Deployment compared | Steps and time for each. A table, not prose | 45s |
| 10 | Resources compared | CPU, RAM, storage, availability. One table | 40s |
| 11 | Performance results | Your measured numbers as a small bar chart. Include the cold start | 60s |
| 12 | Security and reliability | Shared responsibility split; one named risk per environment | 45s |
| 13 | Cost comparison | Local monthly estimate vs free tier vs realistic paid tier | 45s |
| 14 | Challenges faced | Three real ones and how you solved them | 45s |
| 15 | Recommendation | Which environment, and the evidence behind it | 40s |

---

## The demo

Rehearse it and have a fallback. Suggested two minutes:

1. Public cloud URL, sign in — proves the deployment is real and reachable
2. Add a product with quantity below its reorder level — the low-stock badge appears
3. Search for it
4. Dashboard — the alert counters have changed
5. Activity log — the actions were recorded

**Open the cloud URL several minutes before you start.** The free instance spins down
after 15 minutes idle, and a 50-second blank screen in front of the examiner is avoidable.
Keep the local deployment open in a second tab as a fallback, and record a 60-second
screen capture as insurance against the venue's Wi-Fi.

## Challenges — use your real ones

Marks come from the problem and the reasoning, not from claiming everything was smooth.
Genuine ones from this project:

- The original application was a Java console program and could not be deployed to a web
  server or shared between users at all. It was re-implemented as a PHP web application
  while keeping the same domain model and validation rules.
- MySQL and PostgreSQL are different engines. Rather than maintaining two codebases, the
  data layer was written against PDO using SQL both engines accept, with the engine chosen
  from configuration at runtime.
- The local MariaDB privilege table was corrupt, so `GRANT` statements reported success
  but silently had no effect. Diagnosed with `CHECK TABLE` and repaired offline with
  `aria_chk --recover`.
- Windows Firewall blocked inbound port 80, so the application was unreachable from a
  phone on the same Wi-Fi until a scoped inbound rule was added.
- The free cloud tier spins down when idle, so the first request after a quiet period is
  far slower than any local request. Measured and reported rather than hidden.

## Delivery

- Slides carry keywords and images; the detail is spoken, not read
- Every chart needs a title, axis labels and units
- Screenshots must be readable from the back of the room — crop hard, enlarge
- Finish at nine minutes rather than being stopped at ten

## Likely questions

- Why that cloud provider over others?
- What happens when the free database expires after 30 days?
- How would the system behave with 100 concurrent users?
- How are passwords stored, and why is that safe?
- What stops a user injecting SQL through the search box?
- Which is cheaper over three years, and does cheapest mean best here?
- Why is the local deployment faster, if cloud infrastructure is more powerful?
