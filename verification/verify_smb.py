from playwright.sync_api import Page, expect, sync_playwright

def test_smb_client(page: Page):
    # 1. Go to the login page (frontend is on 8080)
    page.goto("http://localhost:8080")

    # 2. Login
    # The default credentials in the samba container in docker-compose:
    # USER=testuser;testpassword
    # SHARE=share

    # Fill in the form
    page.fill('input[name="share"]', r"\\samba\share")
    # Note: Hostname 'samba' resolves within the docker network, but the backend is inside the docker network too.
    # The backend will try to resolve 'samba'. Since they are on the same network 'smb-net', it should work.

    page.fill('input[name="domain"]', "WORKGROUP")
    page.fill('input[name="username"]', "testuser")
    page.fill('input[name="password"]', "testpassword")

    # Click Connect
    page.click('button[type="submit"]')

    # 3. Verify File Manager loads
    # Wait for the file list to appear
    expect(page.locator('.file-list')).to_be_visible(timeout=10000)

    # Check if our test file exists (we created 'hello.txt' in the share)
    expect(page.get_by_text("hello.txt")).to_be_visible()

    # 4. Take screenshot of file manager
    page.screenshot(path="verification/file_manager.png")

    # 5. Create a folder
    page.fill('input[placeholder="New Folder Name"]', "TestFolder")
    page.click('button:text("Create Folder")')

    # Verify folder appears
    expect(page.get_by_text("TestFolder")).to_be_visible()

    # 6. Take screenshot after folder creation
    page.screenshot(path="verification/file_manager_folder.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_smb_client(page)
            print("Verification script finished successfully.")
        except Exception as e:
            print(f"Verification failed: {e}")
            page.screenshot(path="verification/failure.png")
        finally:
            browser.close()
