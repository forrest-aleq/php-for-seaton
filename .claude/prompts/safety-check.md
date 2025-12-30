# Safety Check Prompt

Copy and paste this after Claude writes code:

---

Check the code you just wrote for these problems:

1. **SQL Injection**: Are you putting variables directly in SQL? Use prepared statements instead.

2. **XSS**: Are you echoing variables without htmlspecialchars()?

3. **Passwords**: Are you using md5() or sha1()? Use password_hash() instead.

4. **Secrets**: Are there any passwords or API keys written in the code? They should come from environment variables.

5. **Debug Code**: Any var_dump(), print_r(), die(), or dd() left in?

6. **Fake Data**: Any test@example.com, fake_user, or placeholder values?

7. **TODO Comments**: Anything marked TODO or FIXME that isn't done?

Show me any problems you find and fix them.
