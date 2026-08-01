package com.racikin.app;

import android.Manifest;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothGatt;
import android.bluetooth.BluetoothGattCallback;
import android.bluetooth.BluetoothGattCharacteristic;
import android.bluetooth.BluetoothGattService;
import android.bluetooth.BluetoothManager;
import android.bluetooth.BluetoothSocket;
import android.bluetooth.le.BluetoothLeScanner;
import android.bluetooth.le.ScanCallback;
import android.bluetooth.le.ScanResult;
import android.content.Context;
import android.os.Build;
import android.util.Base64;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

import java.util.ArrayList;
import java.util.List;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.ConcurrentLinkedQueue;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

/**
 * Plugin cetak thermal ESC/POS untuk printer Bluetooth Classic (SPP) dan BLE.
 * JS mengirim payload ESC/POS lengkap (init + logo raster + teks + feed) sebagai base64;
 * plugin ini hanya menyambung ke printer dan menuliskan byte-nya.
 */
@CapacitorPlugin(
    name = "ThermalPrinter",
    permissions = {
        @Permission(alias = "bt", strings = {
            Manifest.permission.BLUETOOTH_CONNECT,
            Manifest.permission.BLUETOOTH_SCAN
        })
    }
)
public class ThermalPrinter extends Plugin {

    private static final UUID SPP_UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");

    private BluetoothAdapter adapter() {
        BluetoothManager bm = (BluetoothManager) getContext().getSystemService(Context.BLUETOOTH_SERVICE);
        return bm != null ? bm.getAdapter() : BluetoothAdapter.getDefaultAdapter();
    }

    private boolean needsRuntimePerm() {
        return Build.VERSION.SDK_INT >= 31; // Android 12+ butuh BLUETOOTH_CONNECT/SCAN
    }
    private boolean hasBtPerm() {
        if (!needsRuntimePerm()) return true;
        return getPermissionState("bt") == com.getcapacitor.PermissionState.GRANTED;
    }

    @PluginMethod
    public void isAvailable(PluginCall call) {
        BluetoothAdapter a = adapter();
        JSObject r = new JSObject();
        r.put("available", a != null);
        r.put("enabled", a != null && a.isEnabled());
        call.resolve(r);
    }

    @PluginMethod
    public void requestPermissions(PluginCall call) {
        if (!needsRuntimePerm() || hasBtPerm()) { permResult(call); return; }
        requestPermissionForAlias("bt", call, "permCb");
    }

    @PermissionCallback
    private void permCb(PluginCall call) { permResult(call); }

    private void permResult(PluginCall call) {
        JSObject r = new JSObject();
        r.put("granted", hasBtPerm());
        call.resolve(r);
    }

    /** Daftar printer Bluetooth Classic yang sudah di-pair. */
    @PluginMethod
    public void listPaired(PluginCall call) {
        if (!hasBtPerm()) { call.reject("Izin Bluetooth belum diberikan."); return; }
        BluetoothAdapter a = adapter();
        if (a == null) { call.reject("Perangkat tidak mendukung Bluetooth."); return; }
        JSArray arr = new JSArray();
        try {
            Set<BluetoothDevice> bonded = a.getBondedDevices();
            if (bonded != null) for (BluetoothDevice d : bonded) {
                JSObject o = new JSObject();
                o.put("name", d.getName() != null ? d.getName() : "(tanpa nama)");
                o.put("address", d.getAddress());
                o.put("type", "classic");
                arr.put(o);
            }
        } catch (SecurityException e) { call.reject("Izin Bluetooth ditolak."); return; }
        JSObject r = new JSObject(); r.put("devices", arr); call.resolve(r);
    }

    /** Scan printer BLE selama beberapa detik. */
    @PluginMethod
    public void scanBle(final PluginCall call) {
        if (!hasBtPerm()) { call.reject("Izin Bluetooth belum diberikan."); return; }
        final BluetoothAdapter a = adapter();
        if (a == null || !a.isEnabled()) { call.reject("Bluetooth mati/tidak tersedia."); return; }
        final BluetoothLeScanner scanner = a.getBluetoothLeScanner();
        if (scanner == null) { call.reject("BLE tidak tersedia."); return; }
        final int seconds = call.getInt("seconds", 6);
        final List<String> seen = new ArrayList<>();
        final JSArray arr = new JSArray();
        final ScanCallback cb = new ScanCallback() {
            @Override public void onScanResult(int type, ScanResult result) {
                try {
                    BluetoothDevice d = result.getDevice();
                    if (d == null || d.getAddress() == null || seen.contains(d.getAddress())) return;
                    seen.add(d.getAddress());
                    JSObject o = new JSObject();
                    String nm = d.getName();
                    o.put("name", nm != null ? nm : "(BLE tanpa nama)");
                    o.put("address", d.getAddress());
                    o.put("type", "ble");
                    arr.put(o);
                } catch (SecurityException ignored) {}
            }
        };
        try {
            scanner.startScan(cb);
        } catch (SecurityException e) { call.reject("Izin scan Bluetooth ditolak."); return; }
        new android.os.Handler(android.os.Looper.getMainLooper()).postDelayed(() -> {
            try { scanner.stopScan(cb); } catch (Exception ignored) {}
            JSObject r = new JSObject(); r.put("devices", arr); call.resolve(r);
        }, seconds * 1000L);
    }

    /** Cetak: address + type (classic|ble) + data (base64 payload ESC/POS). */
    @PluginMethod
    public void print(final PluginCall call) {
        if (!hasBtPerm()) { call.reject("Izin Bluetooth belum diberikan."); return; }
        final String address = call.getString("address");
        final String type = call.getString("type", "classic");
        final String dataB64 = call.getString("data");
        if (address == null || dataB64 == null) { call.reject("Parameter kurang (address/data)."); return; }
        final byte[] payload;
        try { payload = Base64.decode(dataB64, Base64.DEFAULT); }
        catch (Exception e) { call.reject("Data tidak valid."); return; }

        new Thread(() -> {
            try {
                if ("ble".equalsIgnoreCase(type)) printBle(address, payload);
                else printClassic(address, payload);
                JSObject r = new JSObject(); r.put("ok", true);
                getActivity().runOnUiThread(() -> call.resolve(r));
            } catch (Exception e) {
                final String msg = e.getMessage() != null ? e.getMessage() : e.toString();
                getActivity().runOnUiThread(() -> call.reject("Gagal cetak: " + msg));
            }
        }).start();
    }

    // ---------- Classic SPP ----------
    private void printClassic(String address, byte[] data) throws Exception {
        BluetoothAdapter a = adapter();
        if (a == null) throw new Exception("Bluetooth tidak tersedia.");
        BluetoothDevice dev = a.getRemoteDevice(address);
        BluetoothSocket socket = null;
        try {
            try { a.cancelDiscovery(); } catch (SecurityException ignored) {}
            socket = dev.createRfcommSocketToServiceRecord(SPP_UUID);
            socket.connect();
            java.io.OutputStream os = socket.getOutputStream();
            // tulis bertahap agar buffer printer tak jebol
            int chunk = 512;
            for (int i = 0; i < data.length; i += chunk) {
                int end = Math.min(i + chunk, data.length);
                os.write(data, i, end - i);
                os.flush();
                Thread.sleep(20);
            }
            Thread.sleep(400); // beri waktu printer menyelesaikan
        } finally {
            if (socket != null) try { socket.close(); } catch (Exception ignored) {}
        }
    }

    // ---------- BLE ----------
    private volatile BluetoothGatt gatt;
    private volatile BluetoothGattCharacteristic writeChar;
    private volatile int mtu = 20;
    private volatile Exception bleError;
    private CountDownLatch connectLatch, mtuLatch, discoverLatch, writeLatch;

    private void printBle(String address, byte[] data) throws Exception {
        BluetoothAdapter a = adapter();
        if (a == null) throw new Exception("Bluetooth tidak tersedia.");
        BluetoothDevice dev = a.getRemoteDevice(address);
        bleError = null; writeChar = null; mtu = 20;
        connectLatch = new CountDownLatch(1);
        discoverLatch = new CountDownLatch(1);
        mtuLatch = new CountDownLatch(1);

        BluetoothGattCallback cb = new BluetoothGattCallback() {
            @Override public void onConnectionStateChange(BluetoothGatt g, int status, int newState) {
                if (newState == BluetoothGatt.STATE_CONNECTED) {
                    connectLatch.countDown();
                    try { g.requestMtu(512); } catch (SecurityException e) { g.discoverServices(); }
                } else if (newState == BluetoothGatt.STATE_DISCONNECTED) {
                    if (connectLatch.getCount() > 0) { bleError = new Exception("Koneksi BLE gagal."); connectLatch.countDown(); }
                }
            }
            @Override public void onMtuChanged(BluetoothGatt g, int m, int status) {
                if (status == BluetoothGatt.GATT_SUCCESS && m > 23) mtu = m - 3;
                mtuLatch.countDown();
                try { g.discoverServices(); } catch (SecurityException ignored) {}
            }
            @Override public void onServicesDiscovered(BluetoothGatt g, int status) {
                writeChar = pickWritable(g);
                if (writeChar == null) bleError = new Exception("Karakteristik tulis printer tak ditemukan.");
                discoverLatch.countDown();
            }
            @Override public void onCharacteristicWrite(BluetoothGatt g, BluetoothGattCharacteristic c, int status) {
                if (status != BluetoothGatt.GATT_SUCCESS) bleError = new Exception("Tulis BLE gagal (status " + status + ").");
                if (writeLatch != null) writeLatch.countDown();
            }
        };

        try {
            gatt = dev.connectGatt(getContext(), false, cb, BluetoothDevice.TRANSPORT_LE);
            if (!connectLatch.await(12, TimeUnit.SECONDS) || bleError != null) throw bleError != null ? bleError : new Exception("Timeout koneksi BLE.");
            mtuLatch.await(3, TimeUnit.SECONDS); // kalau requestMtu gagal, tetap lanjut
            if (!discoverLatch.await(10, TimeUnit.SECONDS) || writeChar == null) throw bleError != null ? bleError : new Exception("Timeout discover service BLE.");

            boolean noResp = (writeChar.getProperties() & BluetoothGattCharacteristic.PROPERTY_WRITE_NO_RESPONSE) != 0
                    && (writeChar.getProperties() & BluetoothGattCharacteristic.PROPERTY_WRITE) == 0;
            writeChar.setWriteType(noResp ? BluetoothGattCharacteristic.WRITE_TYPE_NO_RESPONSE
                                          : BluetoothGattCharacteristic.WRITE_TYPE_DEFAULT);
            int step = Math.max(20, mtu);
            for (int i = 0; i < data.length; i += step) {
                int end = Math.min(i + step, data.length);
                byte[] part = new byte[end - i];
                System.arraycopy(data, i, part, 0, end - i);
                writeChar.setValue(part);
                writeLatch = new CountDownLatch(1);
                if (!gatt.writeCharacteristic(writeChar)) throw new Exception("writeCharacteristic ditolak.");
                if (noResp) Thread.sleep(12); else { writeLatch.await(4, TimeUnit.SECONDS); if (bleError != null) throw bleError; }
            }
            Thread.sleep(500);
        } finally {
            if (gatt != null) { try { gatt.disconnect(); } catch (Exception ignored) {} try { gatt.close(); } catch (Exception ignored) {} gatt = null; }
        }
    }

    private BluetoothGattCharacteristic pickWritable(BluetoothGatt g) {
        // Utamakan UUID printer yang umum, lalu karakteristik writable pertama.
        String[] pref = {"0000ff02", "0000ffe1", "00002af1", "0000ff01"};
        BluetoothGattCharacteristic first = null;
        for (BluetoothGattService s : g.getServices()) {
            for (BluetoothGattCharacteristic c : s.getCharacteristics()) {
                int p = c.getProperties();
                boolean writable = (p & BluetoothGattCharacteristic.PROPERTY_WRITE) != 0
                                || (p & BluetoothGattCharacteristic.PROPERTY_WRITE_NO_RESPONSE) != 0;
                if (!writable) continue;
                if (first == null) first = c;
                String u = c.getUuid().toString().toLowerCase();
                for (String pf : pref) if (u.startsWith(pf)) return c;
            }
        }
        return first;
    }
}
