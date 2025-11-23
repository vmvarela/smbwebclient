from playwright.sync_api import Page, expect, sync_playwright

def verify_app_loads(page: Page):
    # Go to the app
    page.goto("http://localhost:4321")

    # Check if title is correct (from index.astro)
    expect(page).to_have_title("SMB Web Client")

    # Check if the Login component is rendered (from App.jsx initial state)
    # Looking at App.jsx, if not authenticated, it renders <Login />
    # I don't have the code for Login component but I can guess it has some text or input.
    # Let's wait for the header which is in App.jsx
    expect(page.get_by_role("heading", name="SMB Web Client")).to_be_visible()

    # Take screenshot
    page.screenshot(path="/home/jules/verification/web_client.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            verify_app_loads(page)
        finally:
            browser.close()
