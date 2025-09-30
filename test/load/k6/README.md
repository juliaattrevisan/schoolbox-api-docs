# K6 Load Testing

This directory contains [k6](https://k6.io/) load tests for testing HTTP endpoint performance and page load times across key Schoolbox pages.

## Getting Started

### Installing k6

```bash
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6
```

**Other platforms:**
See https://grafana.com/docs/k6/latest/set-up/install-k6/

### Configuration

1. **Copy the example config:**
   ```bash
   cp config.example.js config.js
   ```

2. **Edit `config.js` with your test credentials:**
```javascript
   export const credentials = [
    {
        username: 'testuser1',
        password: 'password1'
    },
    {
        username: 'testuser2',
        password: 'password2'
    },
    {
        username: 'testuser3',
        password: 'password3'
    }
];
```


## Running Load Tests

### Basic run
```bash
k6 run loadtest.js
```

### Customize the test
Edit `loadtest.js` to modify:
- **Virtual users (VUs):** Number of concurrent users simulating load
- **Duration:** How long the test runs
- **Thresholds:** Performance requirements (e.g., 95% of requests under 3 seconds)

### Understanding the output

After the test completes, k6 provides a summary including:

- **http_req_duration:** Response times (avg, min, max, p95)
- **http_req_failed:** Percentage of failed requests
- **checks:** Pass/fail status for each page tested
- **iterations:** Number of complete user journeys through the site

## What This Test Does

The load test simulates users:
1. Logging in to Schoolbox
2. Visiting key pages in sequence:
    - Dashboard
    - Calendar
    - Timetable
    - Due Work
    - Homepage
    - Grades
3. Pausing between pages (like real users)
4. Repeating the journey multiple times

## Further Reading

- [k6 Documentation](https://k6.io/docs/)
- [k6 Test Types](https://k6.io/docs/test-types/introduction/)
- [k6 Thresholds](https://k6.io/docs/using-k6/thresholds/)
