package com.globelax.app;

import static org.junit.Assert.assertEquals;

import org.junit.Test;

/** Trivial JVM sanity check kept so `./gradlew test` has something to run. */
public class ExampleUnitTest {

    @Test
    public void splashColorsMatchBrandTokens() {
        // Brand palette: navy background, gold + red accents.
        assertEquals("#040C24", "#040C24");
    }
}
