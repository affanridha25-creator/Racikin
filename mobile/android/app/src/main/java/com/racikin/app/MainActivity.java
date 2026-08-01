package com.racikin.app;

import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.webkit.WebView;

import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(ThermalPrinter.class);
        super.onCreate(savedInstanceState);

        // Edge-to-edge konsisten + teruskan inset system bar ke CSS (--sat/--sab/--sal/--sar).
        // WebView Android tak mengisi env(safe-area-inset-*), jadi kita suntik manual agar
        // konten bawah/atas tak ketutup nav/status bar (mis. Samsung S25, Android 15).
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        final WebView wv = getBridge().getWebView();
        final View decor = getWindow().getDecorView();

        // Listener di decorView (penerima inset paling awal) — inject lalu teruskan ke child.
        ViewCompat.setOnApplyWindowInsetsListener(decor, (v, insets) -> {
            injectInsets(wv, insets.getInsets(WindowInsetsCompat.Type.systemBars()));
            return insets;
        });
        ViewCompat.requestApplyInsets(decor);

        // Fallback: baca ulang inset beberapa saat setelah layout (kalau listener telat/tak jalan).
        final Handler h = new Handler(Looper.getMainLooper());
        Runnable poll = () -> {
            WindowInsetsCompat wi = ViewCompat.getRootWindowInsets(decor);
            if (wi != null) injectInsets(wv, wi.getInsets(WindowInsetsCompat.Type.systemBars()));
        };
        h.postDelayed(poll, 400);
        h.postDelayed(poll, 1200);
    }

    private void injectInsets(final WebView wv, final Insets s) {
        if (wv == null || s == null) return;
        float d = getResources().getDisplayMetrics().density; if (d <= 0) d = 1f;
        final int t = Math.round(s.top / d), b = Math.round(s.bottom / d), l = Math.round(s.left / d), r = Math.round(s.right / d);
        final String js = "(function(){var e=document.documentElement.style;"
            + "e.setProperty('--sat','" + t + "px');e.setProperty('--sab','" + b + "px');"
            + "e.setProperty('--sal','" + l + "px');e.setProperty('--sar','" + r + "px');"
            + "e.setProperty('--insets-ready','1');})();";
        wv.post(() -> wv.evaluateJavascript(js, null));
    }
}
