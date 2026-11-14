package com.automeation.store;

import android.annotation.SuppressLint;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.os.Message;
import android.view.KeyEvent;
import android.view.View;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

public class MainActivity extends AppCompatActivity {

    private static final String DEFAULT_URL = "http://172.29.109.35/Store/mobile/";
    private static final String PREFS_NAME = "store_prefs";
    private static final String KEY_SERVER_URL = "server_url";
    private static final int FILE_CHOOSER_REQUEST_CODE = 1001;

    private WebView webView;
    private SwipeRefreshLayout swipeRefresh;
    private ValueCallback<Uri[]> fileUploadCallback;
    private SharedPreferences prefs;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        prefs = getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        webView = findViewById(R.id.webview);
        swipeRefresh = findViewById(R.id.swipe_refresh);

        swipeRefresh.setColorSchemeResources(R.color.primary);
        swipeRefresh.setOnRefreshListener(() -> webView.reload());

        WebSettings ws = webView.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        ws.setDatabaseEnabled(true);
        ws.setAllowFileAccess(true);
        ws.setAllowContentAccess(true);
        ws.setUseWideViewPort(true);
        ws.setLoadWithOverviewMode(true);
        ws.setBuiltInZoomControls(true);
        ws.setDisplayZoomControls(false);
        ws.setCacheMode(WebSettings.LOAD_NO_CACHE);
        ws.setUserAgentString(ws.getUserAgentString() + " StoreAutomeationApp/1.0");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                swipeRefresh.setRefreshing(false);
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                super.onReceivedError(view, request, error);
                swipeRefresh.setRefreshing(false);
                if (request.isForMainFrame()) {
                    showErrorDialog(request.getUrl().toString());
                }
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String u = request.getUrl().toString();
                if (u.startsWith("http://") || u.startsWith("https://")) {
                    return false;
                }
                try {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(u));
                    startActivity(intent);
                    return true;
                } catch (Exception e) {
                    return false;
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                if (fileUploadCallback != null) {
                    fileUploadCallback.onReceiveValue(null);
                }
                fileUploadCallback = filePathCallback;

                Intent intent = fileChooserParams.createIntent();
                try {
                    startActivityForResult(intent, FILE_CHOOSER_REQUEST_CODE);
                } catch (Exception e) {
                    fileUploadCallback = null;
                    return false;
                }
                return true;
            }
        });

        // Long press on logo or empty region to configure server URL
        webView.setOnLongClickListener(v -> {
            showServerConfigDialog();
            return false;
        });

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack();
                } else {
                    finish();
                }
            }
        });

        loadCurrentServerUrl();
    }

    private void loadCurrentServerUrl() {
        String url = prefs.getString(KEY_SERVER_URL, DEFAULT_URL);
        if (url == null || url.trim().isEmpty()) {
            url = DEFAULT_URL;
        }
        webView.loadUrl(url);
    }

    private void showServerConfigDialog() {
        String current = prefs.getString(KEY_SERVER_URL, DEFAULT_URL);
        final EditText input = new EditText(this);
        input.setSingleLine(true);
        input.setText(current);
        input.setSelection(input.getText().length());

        new AlertDialog.Builder(this)
                .setTitle("ตั้งค่า Server URL")
                .setMessage("ระบุ URL หรือ IP ของระบบ Store Automeation:")
                .setView(input)
                .setPositiveButton("บันทึกและเชื่อมต่อ", (dialog, which) -> {
                    String newUrl = input.getText().toString().trim();
                    if (!newUrl.isEmpty()) {
                        if (!newUrl.startsWith("http://") && !newUrl.startsWith("https://")) {
                            newUrl = "http://" + newUrl;
                        }
                        if (!newUrl.endsWith("/")) {
                            newUrl += "/";
                        }
                        prefs.edit().putString(KEY_SERVER_URL, newUrl).apply();
                        webView.loadUrl(newUrl);
                        Toast.makeText(MainActivity.this, "เชื่อมต่อ: " + newUrl, Toast.LENGTH_SHORT).show();
                    }
                })
                .setNegativeButton("ยกเลิก", null)
                .setNeutralButton("ค่าเริ่มต้น", (dialog, which) -> {
                    prefs.edit().putString(KEY_SERVER_URL, DEFAULT_URL).apply();
                    webView.loadUrl(DEFAULT_URL);
                })
                .show();
    }

    private void showErrorDialog(String failedUrl) {
        new AlertDialog.Builder(this)
                .setTitle("ไม่สามารถเชื่อมต่อได้")
                .setMessage("ไม่สามารถเข้าถึง: " + failedUrl + "\n\nกรุณาตรวจสอบว่าต่อ Wi-Fi วงเดียวกัน หรือต้องการแก้ไข Server IP หรือไม่?")
                .setPositiveButton("แก้ไข Server URL", (dialog, which) -> showServerConfigDialog())
                .setNegativeButton("ลองใหม่", (dialog, which) -> webView.reload())
                .show();
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, @Nullable Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == FILE_CHOOSER_REQUEST_CODE) {
            if (fileUploadCallback == null) return;
            Uri[] results = null;
            if (resultCode == RESULT_OK && data != null) {
                if (data.getData() != null) {
                    results = new Uri[]{data.getData()};
                } else if (data.getClipData() != null) {
                    int count = data.getClipData().getItemCount();
                    results = new Uri[count];
                    for (int i = 0; i < count; i++) {
                        results[i] = data.getClipData().getItemAt(i).getUri();
                    }
                }
            }
            fileUploadCallback.onReceiveValue(results);
            fileUploadCallback = null;
        }
    }
}